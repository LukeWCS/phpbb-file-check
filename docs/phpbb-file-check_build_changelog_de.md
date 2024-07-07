### 1.4.0
* Release (2024-07-07)
* Der Dateiname der Konfig Datei wird nicht mehr als statischer Text, sondern als Variable angesprochen.
* Kleinere Änderungen bei den Meldungen (FC_NOTICE, FC_ERROR).
* Die Prüfung der PHP Voraussetzungen (Min/Max Version) war sinnfrei und wurde entfernt.

#### 1.4.0-b2
* Fehlt die FC Konfig Datei `filecheck_config.php`, wird das jetzt als Hinweis gemeldet, sofern ein Abbruch getriggert wird. [Vorschlag von Mike-on-Tour]
* Es gibt jetzt eine explizite Funktion für die Hinweise und diese werden auch nicht mehr in verschiedenen Variablen gesammelt, sondern nur noch in einer.
* Bei einer Vorab-Version von phpBB (zum Beispiel 3.3.12-RC1) hatte FC einen Hinweis ausgegeben, dass die Version nicht ermittelt werden konnte. Ursache waren zu strenge Regeln bei der Prüfung der Versionsnummer, durch die lediglich Release-Versionen akzeptiert wurden. Jetzt kann FC auch mit Vorab-Versionen umgehen und direkt eine entsprechende Fehlermeldung ausgeben. [Gemeldet von Scanialady]

#### 1.4.0-b1
* Es gibt jetzt einen automatischen Download des passenden Prüfsummen-Pakets (ZIP), wodurch es nicht mehr nötig ist, dieses manuell herunterzuladen, zu entpacken und die Prüfsummen-Dateien manuell hochzuladen. Das ZIP wird dabei lediglich vom Server geholt und lokal (im Foren-Root) gespeichert, jedoch nicht entpackt, da FC auf die Dateien im ZIP direkt zugreifen kann. Ist diese automatische Handhabung des ZIPs nicht möglich, z.B. weil der Hoster den Zugriff auf externe Dateien oder aber die ZIP Klasse deaktiviert hat, dann können die Prüfsummen-Dateien weiterhin manuell hochgeladen werden.
  * Um das pro nationaler Support-Seite individuell steuern zu können, gibt es jetzt die neue Datei `filecheck_config.php` in der die entsprechenden Muster für die URL und den ZIP-Namen definiert werden können. Fehlt diese Datei oder die darin enthaltenen Variablen, ist eine automatische Handhabung des Prüfsummen-Pakets nicht möglich und FC schaltet in den manuellen Modus.
  * Als Fallback haben manuell hochgeladene Prüfsummen-Dateien stets Vorrang.
  * In der Einleitung wird zusätzlich angezeigt, aus welcher Quelle (Ordner oder ZIP) die Prüfsummen-Dateien geladen wurden. Dahinter wird in Klammern angezeigt, welche Dateien effektiv aus der Quelle geladen wurden.
* Der Debug-Modus muss nicht mehr direkt im FC Skript aktiviert werden, sondern kann in der neuen Konfig Datei geschaltet werden.
* Passt die Version einer Prüfsummen-Datei nicht zur ermittelten phpBB Version, wird die Ausführung jetzt direkt mit einer Fehlermeldung abgebrochen. So wird verhindert, dass das Tool unnötigerweise mit einer falschen Prüfsummen-Datei ausgeführt wird, wodurch zahlreiche Falschmeldungen verursacht würden. Das konnte dann auftreten, wenn bei einer falschen Prüfsummen-Datei die Version im Dateinamen auf die tatsächlich benötigte phpBB Version geändert wurde.
