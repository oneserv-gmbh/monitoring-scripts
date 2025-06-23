#!/usr/bin/env python

# 2014-07-11 DHT22.py

import time
import atexit

import pigpio

class sensor:
   def __init__(self, pi, gpio, LED=None, power=None):
      self.pi = pi
      self.gpio = gpio
      self.LED = LED
      self.power = power

      if power is not None:
         pi.write(power, 1)  # Switch sensor on.
         time.sleep(2)

      self.powered = True
      self.cb = None
      atexit.register(self.cancel)
      self.bad_CS = 0  # Bad checksum count.
      self.bad_SM = 0  # Short message count.
      self.bad_MM = 0  # Missing message count.
      self.bad_SR = 0  # Sensor reset count.
      # Power cycle if timeout > MAX_TIMEOUTS.
      self.no_response = 0
      self.MAX_NO_RESPONSE = 2
      self.rhum = -999
      self.temp = -999
      self.tov = None
      self.high_tick = 0
      self.bit = 40
      pi.set_pull_up_down(gpio, pigpio.PUD_OFF)
      pi.set_watchdog(gpio, 0)  # Kill any watchdogs.
      self.cb = pi.callback(gpio, pigpio.EITHER_EDGE, self._cb)
   def _cb(self, gpio, level, tick):
      diff = pigpio.tickDiff(self.high_tick, tick)
      if level == 0:
         # Edge length determines if bit is 1 or 0.
         if diff >= 50:
            val = 1
            if diff >= 200:   # Bad bit?
               self.CS = 256  # Force bad checksum.
         else:
            val = 0
         if self.bit >= 40:  # Message complete.
            self.bit = 40
         elif self.bit >= 32:  # In checksum byte.
            self.CS  = (self.CS << 1)  + val
            if self.bit == 39:
               # 40th bit received.
               self.pi.set_watchdog(self.gpio, 0)
               self.no_response = 0
               total = self.hH + self.hL + self.tH + self.tL
               if (total & 255) == self.CS:  # Is checksum ok?
                  self.rhum = ((self.hH << 8) + self.hL) * 0.1
                  if self.tH & 128:  # Negative temperature.
                     mult = -0.1
                     self.tH = self.tH & 127
                  else:
                     mult = 0.1
                  self.temp = ((self.tH << 8) + self.tL) * mult
                  self.tov = time.time()
                  if self.LED is not None:
                     self.pi.write(self.LED, 0)
               else:
                  self.bad_CS += 1
         elif self.bit >= 24:  # in temp low byte
            self.tL = (self.tL << 1) + val
         elif self.bit >= 16:  # in temp high byte
            self.tH = (self.tH << 1) + val
         elif self.bit >= 8:  # in humidity low byte
            self.hL = (self.hL << 1) + val
         elif self.bit >= 0:  # in humidity high byte
            self.hH = (self.hH << 1) + val
         else:               # header bits
            pass
         self.bit += 1
      elif level == 1:
         self.high_tick = tick
         if diff > 250000:
            self.bit = -2
            self.hH = 0
            self.hL = 0
            self.tH = 0
            self.tL = 0
            self.CS = 0
      else:  # level == pigpio.TIMEOUT:
         self.pi.set_watchdog(self.gpio, 0)
         if self.bit < 8:       # Too few data bits received.
            self.bad_MM += 1    # Bump missing message count.
            self.no_response += 1
            if self.no_response > self.MAX_NO_RESPONSE:
               self.no_response = 0
               self.bad_SR += 1  # Bump sensor reset count.
               if self.power is not None:
                  self.powered = False
                  self.pi.write(self.power, 0)
                  time.sleep(2)
                  self.pi.write(self.power, 1)
                  time.sleep(2)
                  self.powered = True
         elif self.bit < 39:    # Short message receieved.
            self.bad_SM += 1    # Bump short message count.
            self.no_response = 0
         else:                  # Full message received.
            self.no_response = 0
   def temperature(self):
      """Return current temperature."""
      return self.temp
   def humidity(self):
      """Return current relative humidity."""
      return self.rhum
   def staleness(self):
      """Return time since measurement made."""
      if self.tov is not None:
         return time.time() - self.tov
      else:
         return -999
   def bad_checksum(self):
      """Return count of messages received with bad checksums."""
      return self.bad_CS
   def short_message(self):
      """Return count of short messages."""
      return self.bad_SM
   def missing_message(self):
      """Return count of missing messages."""
      return self.bad_MM
   def sensor_resets(self):
      """Return count of power cycles because of sensor hangs."""
      return self.bad_SR
   def trigger(self):
      """Trigger a new relative humidity and temperature reading."""
      if self.powered:
         if self.LED is not None:
            self.pi.write(self.LED, 1)
         self.pi.write(self.gpio, pigpio.LOW)
         time.sleep(0.017)  # 17 ms
         self.pi.set_mode(self.gpio, pigpio.INPUT)
         self.pi.set_watchdog(self.gpio, 200)
   def cancel(self):
      """Cancel the DHT22 sensor."""
      self.pi.set_watchdog(self.gpio, 0)
      if self.cb is not None:
         self.cb.cancel()
         self.cb = None
if __name__ == "__main__":
    import argparse
    import time
    import sys
    import pigpio
    import DHT22

    def parse_range(value):
        try:
            min_val, max_val = map(float, value.split(":"))
            return (min_val, max_val)
        except ValueError:
            raise argparse.ArgumentTypeError("Bereich muss im Format min:max angegeben werden, z.B. 18.5:25.0")

    # Argumente einlesen
    parser = argparse.ArgumentParser(description="Nagios Plugin für DHT22 mit Schwellwertbereichen")
    parser.add_argument("-P", "--pin", dest="GPIOpin", type=int, help="GPIO Pin des DHT22 Sensors", required=True)
    parser.add_argument("-wt", "--warningtemp", dest="wtemp", type=parse_range, help="Warnbereich für Temperatur (min:max)", required=True)
    parser.add_argument("-ct", "--criticaltemp", dest="ctemp", type=parse_range, help="Kritischer Bereich für Temperatur (min:max)", required=True)
    parser.add_argument("-wh", "--warninghum", dest="whum", type=parse_range, help="Warnbereich für Luftfeuchtigkeit (min:max)", required=True)
    parser.add_argument("-ch", "--criticalhum", dest="chum", type=parse_range, help="Kritischer Bereich für Luftfeuchtigkeit (min:max)", required=True)
    args = parser.parse_args()

    # Sensor einrichten
    INTERVAL = 3
    pi = pigpio.pi()
    s = DHT22.sensor(pi, args.GPIOpin, LED=16, power=8)

    # 3 Messwerte sammeln
    temperatures = []
    humidities = []

    for i in range(3):
        s.trigger()
        time.sleep(0.2)
        temperatures.append(s.temperature())
        humidities.append(s.humidity())
        time.sleep(1)

    # Durchschnitt berechnen
    average_temp = sum(temperatures) / len(temperatures)
    average_hum = sum(humidities) / len(humidities)

    # Status bestimmen
    status = "OK"
    exit_code = 0

    def check_range(value, warn_range, crit_range):
        if value < crit_range[0] or value > crit_range[1]:
            return "CRITICAL", 2
        elif value < warn_range[0] or value > warn_range[1]:
            return "WARNING", 1
        else:
            return "OK", 0

    temp_status, temp_code = check_range(average_temp, args.wtemp, args.ctemp)
    hum_status, hum_code = check_range(average_hum, args.whum, args.chum)

    # Schlimmsten Status wählen
    if 2 in [temp_code, hum_code]:
        status = "CRITICAL"
        exit_code = 2
    elif 1 in [temp_code, hum_code]:
        status = "WARNING"
        exit_code = 1

    # Ausgabe
    print(f"{status} - Temperatur: {average_temp:.2f}°C, Luftfeuchtigkeit: {average_hum:.2f}% | "
          f"temperature={average_temp:.2f};{args.wtemp[0]}:{args.wtemp[1]};{args.ctemp[0]}:{args.ctemp[1]};0;200 "
          f"humidity={average_hum:.2f};{args.whum[0]}:{args.whum[1]};{args.chum[0]}:{args.chum[1]};0;100")
    sys.exit(exit_code)
