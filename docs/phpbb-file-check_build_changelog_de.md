### 1.5.0-b2
* Fix: Beim Unexpected-Check sollte eine Abfrage verhindern, dass eine bestimmte Schleife unnötig oft ausgeführt wird, jedoch war diese Abfrage fehlerhaft. Dadurch wurden - als Beispiel - bei phpBB 3.3.15 insgesamt 3959 Durchläufe ausgeführt, anstatt 561.
* Der Ordner `{root}/ext/` wird jetzt ebenfalls ignoriert.
* Code Optimierung.

### 1.5.0-b1
* Neue Prüfung auf unerwartete Dateien:
  * Im Anschluss des bisherigen Prüflaufs der auf Existenz und Integrität prüft, wird noch eine weitere, separate Prüfung ausgeführt, die darauf spezialisiert ist, unerwartete Dateien zu ermitteln. Das Ergebnis dieser neuen Prüfung wird dann in einer separaten, zweiten Liste angezeigt.
  * Da es im Bericht nun zwei Listen gibt, haben diese entsprechende Überschriften bekommen:
    * "List of core files with anomalies:"  
	* "List of unexpected files:"
  * Folgende Ordner werden von der neuen Prüfung ignoriert, da diese traditionell viele individuelle Dateien enthalten:
    * `{root}/`
	* `{root}/cache/`
	* `{root}/files/`
	* `{root}/images/`
	* `{root}/store/`
  * Die neue Prüfung verlängert die Laufzeit von FC deutlich, etwa um den Faktor 2-3. Ob das aber auch spürbar ist, hängt stark vom Web-Server ab, wie potent dieser ist und welche Dateien von phpBB sich gerade noch im Cache des Web-Servers befinden.
* Im Bericht sind die Zeilen "Warnings:" und "Different files:" jetzt vertauscht positioniert.

### 1.4.6
* Release (2025-03-30)

### 1.4.5
* Release (2025-02-15)
* Debug Code entfernt.
* Code Optimierung.

#### 1.4.5-b1
* Um den zusätzlichen Aufwand bei eingeschränkten Hosting-Paketen zu reduzieren, bei denen sämtliche Download-Funktionen deaktiviert sind und/oder die ZIP-Funktion deaktiviert ist, generiert FC in einer solchen Situation jetzt eine individuelle 3-Schritt-Anleitung in Abhängigkeit der vorhandenen Hosting-Funktionen und der installierten phpBB Version. Dadurch werden die notwendigen Schritte der Anleitung 4.b im offiziellen FC Foren-Thema auf ein Minimum reduziert und diese Anleitung muss im Normalfall gar nicht mehr gesichtet werden, da FC direkt selber die nötigen Schritte anzeigen kann.

### 1.4.4
* Release (2024-11-23)

### 1.4.3
* Release (2024-08-18)

### 1.4.2
* Release (2024-07-21)

### 1.4.1
* Release (2024-07-10)
* Vor dem Speichern des ZIPs wird zuerst geprüft, ob die übertragenen Daten überhaupt ein ZIP enthalten. Wenn nicht, wird ein entsprechender Hinweis generiert.
* Konnte das ZIP nicht im Root gefunden werden, wird dafür ein Hinweis generiert. [Rückmeldung von Kirk (phpBB.de)]
* Probleme bezüglich ZIP werden nicht mehr als `FC_ERROR` getriggert, sondern nur noch als `FC_NOTICE`, da diese Probleme streng genommen für FC noch keinen Grund für einen Abbruch darstellen. Dazu musste der Code an mehreren Stellen geändert werden.
* Code Optimierung.

#### 1.4.1-b2
* Fix: Bei einem Abbruch wurde kein Hinweis für den deaktivierten Socket Dienst generiert. Ich habe vergessen das einzubauen. [Rückmeldung von Kirk (phpBB.de)]

#### 1.4.1-b1
* Für den Download des Prüfsummen-Pakets wird jetzt zusätzlich cURL und Socket verwendet. Ist beides nicht vorhanden, wird die bisherige Funktion `file_get_contents()` verwendet.
* Beim Download des Prüfsummen-Pakets werden die Hinweise bez. Konfig und Dienste jetzt zuerst generiert, damit diese auch bei einer DL Fehlermeldung ebenfalls zur Verfügung stehen.
* Die Dienste und ihre Zustände werden explizit im Bericht bei den PHP Informationen gelistet.

### 1.4.0
* Release (2024-07-07)
* Der Dateiname der Konfig Datei wird nicht mehr als statischer Text, sondern als Variable angesprochen.
* Kleinere Änderungen bei den Meldungen (FC_NOTICE, FC_ERROR).
* Die Prüfung der PHP Voraussetzungen (Min/Max Version) war sinnfrei und wurde entfernt.

#### 1.4.0-b2
* Fehlt die FC Konfig Datei `filecheck_config.php`, wird das dafür jetzt ein Hinweis generiert. [Vorschlag von Mike-on-Tour]
* Es gibt jetzt eine explizite Funktion für die Hinweise und diese werden auch nicht mehr in verschiedenen Variablen gesammelt, sondern nur noch in einer.
* Bei einer Vorab-Version von phpBB (zum Beispiel 3.3.12-RC1) hatte FC einen Hinweis ausgegeben, dass die Version nicht ermittelt werden konnte. Ursache waren zu strenge Regeln bei der Prüfung der Versionsnummer, durch die lediglich Release-Versionen akzeptiert wurden. Jetzt kann FC auch mit Vorab-Versionen umgehen und direkt eine entsprechende Fehlermeldung ausgeben. [Gemeldet von Scanialady]

#### 1.4.0-b1
* Es gibt jetzt einen automatischen Download des passenden Prüfsummen-Pakets (ZIP), wodurch es nicht mehr nötig ist, dieses manuell herunterzuladen, zu entpacken und die Prüfsummen-Dateien manuell hochzuladen. Das ZIP wird dabei lediglich vom Server geholt und lokal (im Foren-Root) gespeichert, jedoch nicht entpackt, da FC auf die Dateien im ZIP direkt zugreifen kann. Ist diese automatische Handhabung des ZIPs nicht möglich, z.B. weil der Hoster den Zugriff auf externe Dateien oder aber die ZIP Klasse deaktiviert hat, dann können die Prüfsummen-Dateien weiterhin manuell hochgeladen werden.
  * Um das pro nationaler Support-Seite individuell steuern zu können, gibt es jetzt die neue Datei `filecheck_config.php` in der die entsprechenden Muster für die URL und den ZIP-Namen definiert werden können. Fehlt diese Datei oder die darin enthaltenen Variablen, ist eine automatische Handhabung des Prüfsummen-Pakets nicht möglich und FC schaltet in den manuellen Modus.
  * Als Fallback haben manuell hochgeladene Prüfsummen-Dateien stets Vorrang.
  * In der Einleitung wird zusätzlich angezeigt, aus welcher Quelle (Ordner oder ZIP) die Prüfsummen-Dateien geladen wurden. Dahinter wird in Klammern angezeigt, welche Dateien effektiv aus der Quelle geladen wurden.
* Der Debug-Modus muss nicht mehr direkt im FC Skript aktiviert werden, sondern kann in der neuen Konfig Datei geschaltet werden.
* Passt die Version einer Prüfsummen-Datei nicht zur ermittelten phpBB Version, wird die Ausführung jetzt direkt mit einer Fehlermeldung abgebrochen. So wird verhindert, dass das Tool unnötigerweise mit einer falschen Prüfsummen-Datei ausgeführt wird, wodurch zahlreiche Falschmeldungen verursacht würden. Das konnte dann auftreten, wenn bei einer falschen Prüfsummen-Datei die Version im Dateinamen auf die tatsächlich benötigte phpBB Version geändert wurde.
