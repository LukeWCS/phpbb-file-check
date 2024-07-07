### 1.4.0
(2024-07-07)

* Es gibt jetzt einen automatischen Download des passenden Prüfsummen-Pakets (ZIP); dadurch ist es nicht mehr nötig, dieses manuell herunterzuladen, zu entpacken und die Prüfsummen-Dateien einzeln hochzuladen. Das ZIP wird im Foren-Root gespeichert, damit FC darauf zugreifen kann. Ist diese automatische Handhabung des Prüfsummen-Pakets nicht möglich, z.B. weil der Hoster den Zugriff auf externe Dateien oder aber die ZIP Klasse deaktiviert hat, dann können die Prüfsummen-Dateien wie bisher manuell hochgeladen werden.
  * Um das pro nationaler Support-Seite individuell steuern zu können, gibt es jetzt die neue Datei `filecheck_config.php` in der die entsprechenden Muster für die URL und den ZIP-Namen vom jeweiligen nationalen Support Team definiert werden können. Fehlt diese Datei oder die darin enthaltenen Variablen, ist eine automatische Handhabung des Prüfsummen-Pakets nicht möglich und FC schaltet in den manuellen Modus.
  * Als Fallback haben manuell hochgeladene Prüfsummen-Dateien stets Vorrang.
  * In der Einleitung wird zusätzlich angezeigt, aus welcher Quelle (Ordner oder ZIP) die Prüfsummen-Dateien geladen wurden. Dahinter wird in Klammern angezeigt, welche Dateien effektiv aus der Quelle geladen werden konnten:
    * 1 = primäre Prüfsummen-Datei
    * 2 = sekundäre Prüfsummen-Datei
    * I = Ignorieren-Liste
    * E = Ausnahme-Liste
* Passt die Version einer Prüfsummen-Datei nicht zur ermittelten phpBB Version, wird die Ausführung jetzt direkt mit einer Fehlermeldung abgebrochen. So wird verhindert, dass das Tool unnötigerweise mit einer falschen Prüfsummen-Datei ausgeführt werden kann, wodurch zahlreiche Falschmeldungen verursacht würden. Das konnte dann auftreten, wenn bei einer falschen Prüfsummen-Datei die Version im Dateinamen auf die tatsächlich benötigte phpBB Version geändert wurde.
* Bei einer Vorab-Version von phpBB (zum Beispiel 3.3.12-RC1) hatte FC einen Hinweis ausgegeben, dass die Version nicht ermittelt werden konnte. Ursache waren zu strenge Regeln bei der Prüfung der Versionsnummer, durch die lediglich Release-Versionen berücksichtigt wurden. Jetzt kann FC auch mit Vorab-Versionen umgehen und eine passende Fehlermeldung ausgeben. [Gemeldet von Scanialady (phpBB.de)]
* Um den Debug-Modus bei Bedarf zu aktivieren, muss nicht mehr das FC Skript selber geändert werden, sondern kann in der neuen Konfig Datei geregelt werden.
* Die Prüfung der PHP Voraussetzungen (Min/Max Version) war in dieser Form sinnfrei und wurde entfernt.

### 1.3.0
(2024-05-29)

* Bei Ausführung der Prüfung sollte eigentlich die Einleitung mit den Versions-Informationen vorab an den Browser gesendet werden, damit erkennbar ist, dass das Tool ausgeführt wird. Die bisher verwendete Methode ist jedoch abhängig vom Server (Apache, nginx, IIS usw.) sowie von der Cache-Konfiguration des Hosters und konnte deshalb nur in sehr seltenen Fällen funktionieren und wurde entfernt. Stattdessen wird jetzt mit Sessions gearbeitet und das Skript automatisch ein zweites Mal aufgerufen, wodurch es möglich wird, die Einleitung vorab anzuzeigen. Dadurch wird beim Start des Tools eine anfängliche "weiße Seite" vermieden, da jetzt direkt signalisiert werden kann, dass alle benötigten Dateien vorhanden sind und das Tool korrekt ausgeführt wird.
* In der Einleitung wird der Versions-Modus (Auto/Manually) nicht mehr hinter beiden MD5 Versionen angezeigt, sondern nur noch einmal in einer separaten Zeile.
* Am Ende des Berichts werden zusätzliche Informationen in einem separaten Abschnitt gelistet:
  * Laufzeit von File Check
  * Neu: Maximale PHP Ausführungszeit (Hoster-abhängig)
  * Neu: Speicherbedarf (Spitze) von File Check
  * Neu: Maximaler PHP Speicherbedarf (Hoster-abhängig)
* In `filecheck_ignore.txt` dürfen die einzelnen Zeilen mit RegEx Regeln kein führendes und abschliessendes `/` mehr enthalten, das wird jetzt als Fehler gemeldet.
* Der BBcode `[code]` wird im Browser nur noch schwach dargestellt, da dieser nicht zum eigentlichen Bericht gehört und nur beim Einfügen des Berichts in einem Forenbeitrag relevant ist.
* Code Optimierung.

### 1.2.2
(2023-12-24)

* Bei einer Datei die 0 Bytes aufweist, jedoch laut Hash nicht leer sein sollte, wird jetzt statt `* CHANGED` die Stufe `! WARNING` gemeldet mit dem Text `has 0 bytes`.
* Sonstiges:
  * JS: 
    * Sollte das Kopieren in die Zwischenablage fehlschlagen, bleibt die Fehlermeldung jetzt bestehen und wird nicht mehr nach 3 Sekunden ausgeblendet.
    * Um zukünftige Probleme mit Sonderzeichen zu vermeiden, wird jetzt nicht mehr das HTML des Berichts, sondern der dargestellte Text kopiert.
  * Code Optimierung.

### 1.2.1
(2023-12-13)

* Fix: Wenn beim Klick auf den Button bereits teilweise Text ausgewählt war, dann wurde nur dieser markierte Bereich in die Zwischenablage kopiert, anstatt dem vollständigen Bericht.
* Nach dem Klick auf den Button signalisiert zusätzlich ein Icon über den Vorgang: Grüner Haken (erfolgreich), rotes Kreuz (fehlgeschlagen).
* Sonstiges:
  * JS: 
    * Da das Kopieren von Text in die Zwischenablage mittels `document.execCommand('copy')` zugunsten der Clipboard API missbilligt ist und seit Jahren als DEPRECATED eingestuft ist, wird jetzt vorrangig die Clipboard API verwendet. Sollte diese nicht vorhanden sein, z.B. in einem Kontext wo SSL fehlt, wird als Fallback die alte Methode verwendet, solange diese noch funktioniert.
    * Code verbessert.

### 1.2.0
(2023-12-10)

* Freigegeben für PHP 8.3.
* Bei Verwendung im Browser folgende Änderungen: 
  * Der Bericht wird jetzt inklusive dem BBcode `[code][/code]` generiert, damit man den Bericht direkt in einem Forenbeitrag einfügen kann.
  * Am Ende des Berichts wird jetzt ein Button angeboten, mit dem der Bericht direkt in die Zwischenablage kopiert werden kann. Eine kurzfristige Anzeige (3 Sekunden) rechts neben dem Button informiert darüber, ob der Vorgang erfolgreich war.
* Sonstiges:
  * Prüfung der Voraussetzungen angepasst.
  * Eine JS Funktion für das Kopieren in die Zwischenablage eingebaut.
  * Inline-CSS auf Internal-CSS geändert.

### 1.1.3
(2023-10-07)

* Beim Start wird jetzt die PHP Version geprüft und bei falschen Voraussetzungen das Skript mit Fehlermeldung abgebrochen.
* Code Optimierung.

### 1.1.2
(2023-09-09)

* Fehlerbehandlung für die phpBB Datei `includes/constants.php` erweitert:
  * Kann die Datei nicht erfolgreich gelesen werden, wird das explizit als Hinweis gemeldet.
  * Kann aus der Datei die phpBB Version nicht ermittelt werden, wird das explizit als Hinweis gemeldet.
* Dateinamen in Prüfsummen-Dateien werden strenger geprüft; die erlaubten Zeichen sind jetzt explizit vorgegeben. Bisher wurde nur auf einige wenige unerlaubte Zeichen geprüft.
* Die externe Ausnahme-Liste wird jetzt ebenfalls auf erlaubte Zeichen geprüft. Dafür wird mit demselben Muster geprüft, wie bei den Prüfsummen-Dateien.
* Kleine Änderungen.
* Code Optimierung.

### 1.1.1
(2023-08-25)

* Fix: Wenn die Ignorieren-Liste `filecheck_ignore.txt` vorhanden war und diese mehrere ungültige RegEx Einträge enthielt, wurde nur der letzte gemeldet.
* Fix: Bei Ausführung im Browser wurden die abschliessenden HTML Tags nicht generiert, wenn mit einer Fehlermeldung abgebrochen wurde.
* Strengere Regeln bei den Prüfsummen-Dateien:
  * Es werden nur noch Prüfsummen akzeptiert, die im Binär-Modus erstellt wurden.
  * Dateinamen werden auf unerlaubte Zeichen geprüft.
* Kann die phpBB Datei `includes/constants.php` nicht gefunden werden, wird das jetzt explizit als Hinweis gemeldet.
* Im RegEx unnötige non-greedy Operatoren entfernt, da die Reichweite bereits per Zeichen-Klasse vorgegeben ist und ohnehin keine Abweichung erlaubt.
* Kleine Änderungen.
* Code Optimierung.
* Haupt-Code kommentiert.

### 1.1.0 
(2023-07-20)

* Fix: Bei Verwendung in der Shell wurde bei einer Fehlermeldung bezüglich fehlender Prüfsummen-Datei der HTML Footer generiert.
* Um die Problematik der Abweichungen bei phpBB.de und phpBB.com Paketen in den Griff zu bekommen, mussten einige Änderungen vorgenommen werden. Die Basis für FC ist nicht mehr das deutsche Komplettpaket, sondern das phpBB.com Paket. Um alle zusätzlichen und abweichenden Dateien des deutschen Komplettpakets berücksichtigen zu können, wird nun eine zweite Prüfsummen-Datei unterstützt, in der alle Abweichungen gegenüber dem phpBB.com Paket enthalten sind. FC kombiniert dann während der Ausführung beide Prüfsummen-Dateien. Somit können nun auch die abweichenden Dateien aussagekräftig geprüft werden, die bisher je nach Installationspaket möglicherweise falsch als `* CHANGED` gemeldet wurden.
* Die Datei `config.php` wird nicht mehr ignoriert, sondern auf Existenz geprüft. Ausserdem wird geprüft, ob diese Datei 0 Bytes hat (In diesem Fall wird eine Warnung erzeugt). [Vorschlag von Scanialady (phpBB.de)]
* In der Versionsübersicht ist die MD5 Version jetzt nach phpBB.de und phpBB.com unterteilt.
* Neue Meldungs-Typen:
  * `  NOTICE`
  * `! WARNING`
* Bei den Meldungen wird in den geschweiften Klammern jetzt auch die Nummer der Prüfsummen-Datei vorangestellt.
* Änderungen in der Zusammenfassung:
  * Entfernt: Anzahl der gültigen Hashes, da eine fehlerhafte Prüfsummen-Datei ohnehin nicht mehr akzeptiert wird.
  * Neu: Anzahl der Hinweise (NOTICE), sofern welche vorhanden sind.
  * Neu: Anzahl der Warnungen (WARNING), sofern welche vorhanden sind.
* Die Prüfsummen-Dateien werden mit ungültigem oder fehlendem Versions-Merkmal nicht mehr akzeptiert. In diesem Fall wird mit Fehlermeldung abgebrochen.
* Die externe Ignorieren-Liste wird jetzt, sofern vorhanden, auf gültiges RegEx geprüft. Ist ungültiges RegEx vorhanden, wird mit Fehlermeldung abgebrochen.
* Fehlerbehandlung auch an anderen Stellen erweitert und Fehlertoleranz bezüglich FC Dateien deutlich reduziert. Das betrifft besonders die Prüfsummen-Dateien.
* Wenn FC bei Erkennung eines User-Agent-Strings den Bericht im HTML Format ausliefert, ist jetzt ein Meta Tag enthalten, der Suchmaschinen Indizierung verhindern soll. Das kann hilfreich sein, wenn vergessen wurde FC nach Benutzung zu löschen. Das funktioniert natürlich nur bei Crawlern, die sich auch an die Regeln halten. Trotzdem sollte FC auch weiterhin vom FTP Server gelöscht werden, wenn es nicht mehr benötigt wird.
* Code Optimierung.

### 1.0.0
(2023-07-12)

* Erste öffentliche Version.
* Ausser Versionsänderung keinen Unterschied zu 0.5.1.

### 0.5.1
(2023-07-11)

* Die Zeile mit der Variable für den Debug-Modus ist kein Kommentar mehr, sondern eine aktive Codezeile. Die Variable ist per Standard mit `false` belegt und kann bei Bedarf auf `true` gesetzt werden.
* Nachfolgende Ordner wurden zu den internen Ausnahmen hinzugefügt:
  * `docs/`
* Auch Fehlermeldungen die einen Abbruch von FC zur Folge haben, zum Beispiel bei einer fehlenden Prüfsummen-Datei, sind jetzt in ein HTML Gerüst eingebettet, sofern FC im Browser ausgeführt wird.
* Ausserdem wird jetzt zumindest der FC Titel immer ausgegeben, also auch bei Abbruch.

### 0.5.0
(2023-07-10)

* Da ich festgestellt habe, dass in den phpBB Paketen einige überflüssige Dateien vorhanden sind, mussten weitere Einträge zur Ignorieren-Liste hinzugefügt werden. Bei den besagten Dateien handelt es sich um git Dateien, die in einem Distributionspaket für Endbenutzer eigentlich nichts zu suchen haben. Damit diese Dateien nicht umständlich einzeln hinzugefügt werden müssen, was auch nicht zukunftssicher wäre, wurde die Ignorieren-Funktion von einem simplen Textvergleich auf RegEx umgestellt. Dadurch ist die Ignorieren-Funktion nun erheblich flexibler verwendbar.
* Um genau verfolgen zu können, welche Dateien von FC ignoriert und welche per Ausnahme ausgeschlossen wurden, kann man jetzt bei Bedarf den neuen Debug-Modus aktivieren. Dazu muss im FC Skript einfach die Zeile `$debug_mode = true` aktiviert werden. Dadurch werden folgende zusätzliche Anzeigen generiert:
  * Im Titel der Zusatz `(DEBUG MODE)`.
  * In der Meldungsliste bei Ausschlüssen explizit ob die Regel Ignoriert (`- IGNORED`) oder Ausnahme (`- EXCEPTION`) angewendet wurde.
  * In der Zusammenfassung 2 neue Zähler für Ignorierte und Ausnahmen.
* RegEx für die Plausibilitätsprüfung der MD5 Einträge sowie der phpBB Version in der MD5 Datei strikter gestaltet.
* Mehrere Texte und auch Code neutral gestaltet in Bezug auf den verwendeten Hash Algorithmus. Somit ist ein zukünftiger Wechsel auf einen anderen Hash Algorithmus einfacher.
* Kleine Code Verbesserungen.

### 0.4.0
(2023-07-08)

* Bei Dateizugriffen werden statt relative Pfade jetzt absolute Pfade verwendet. In Meldungen werden nach wie vor relative Pfade angezeigt.
* Die Einleitung "Please wait, we are checking x files..." war nicht korrekt, da es von den gültigen MD5 Einträgen sowie von den Listen der Ignorierten und Ausnahmen abhängt, wieviele Dateien tatsächlich geprüft werden. Darum Einleitung geändert in "Please wait, x MD5 entries are being processed...".
* Neue Information in der Zusammenfassung: Wieviele gültige MD5 Einträge effektiv vorhanden sind. Nur MD5 Einträge, die die Plausibilitätsprüfung bestehen, werden als gültige Einträge gezählt.
* Neue Information in der Zusammenfassung: Wieviele Dateien effektiv überprüft wurden. Hier sind die Ignorierten und Ausnahmen also abgezogen.
* Damit Admins nicht durch die Anzeige "ERRORS total" in der Zusammenfassung irritiert werden, wird diese Zeile jetzt nur noch ausgegeben, wenn auch tatsächlich ein FC Fehler vorliegt.
* Die letzte Zeile in der Prüfsummen-Datei, in der die phpBB Version vorhanden sein muss, wird jetzt auf Plausibilität geprüft. Handelt es sich nicht um eine gültige Version mit dem Muster "x.y.z", wird bei der MD5 Version "{unknown}" angezeigt. Des Weiteren wird diese Zeile bei einem Versionsfehler auch nicht aus der MD5 Liste entfernt, wodurch die fehlerhafte Zeile automatisch durch die MD5 Plausibilitätsprüfung gemeldet wird.
* Kleine Code Verbesserungen.

### 0.3.0
(2023-07-06)

* Ab dieser Version kann FC selbständig die richtige MD5 laden, sofern vorhanden. Dazu muss die passende `filecheck_x.y.z.md5` vorhanden sein. Dieser Modus wird dann durch den Zusatz "(auto)" signalisiert. 
* Der automatische Modus kann jederzeit mittels `filecheck.md5` übersteuert werden. In diesem Fall wird der manuelle Modus aktiviert und alle anderen `filecheck_x.y.z.md5` Dateien werden ignoriert. Dieser Modus wird dann durch den Zusatz "(manually)" signalisiert. Das kann in seltenen Fällen notwendig sein, wenn FC die phpBB Version nicht ermitteln kann.
* FC und MD5 Dateien sind jetzt in getrennten Archiven organisiert, so wie es später dann auch bei Veröffentlichung auf phpBB.de der Fall sein wird.
* Bei Verwendung im Browser wurde bisher noch der alte Seitentitel "phpBB CheckFiles" verwendet. Jetzt wird der korrekte Titel angezeigt.
* Code Optimierung.
* Kleine Änderungen.
* Liste der Eigenschaften und Anleitung aktualisiert.

### 0.2.1
(2023-07-03)

* Fix: Bei PHP <8.0 wurde "Uncaught Error: Call to undefined function str_starts_with()" erzeugt. [Meldung von Scanialady (phpBB.de)]

### 0.2.0
(2023-07-03)

* Fix: Bei fehlender `constants.php` wurde "Warning: Undefined variable" erzeugt. Im Prototyp (0.0.x) war das bereits berücksichtigt, jedoch nicht mehr ab 0.1.0.
* Umbenannt von "phpBB CheckFiles" zu "phpBB File Check". Das betrifft auch die Dateinamen und die Anleitung.
* Die Zeilen des Berichts werden nicht mehr einzeln ausgegeben, sondern in einem Puffer gesammelt und dann blockweise ausgegeben. Das kann bei sehr vielen Abweichungen noch ein bisschen Zeit einsparen.
* Die Anzahl Zeichen für rechtsbündig ausgerichtete Zahlen ist nicht mehr auf 4 fixiert, sondern wird dynamisch anhand der Anzahl Zeilen in der Prüfsummen-Datei ermittelt.
* Die Liste der Fehlermeldungen ist jetzt in 2 Bindestrich-Linien eingefasst.
* Es gibt jetzt die Möglichkeit, Ignorierte und Ausnahmen in einer externen Datei definieren zu können, ohne das Skript ändern zu müssen: `filecheck_ignore.txt` und `filecheck_exceptions.txt`. Das ist eine der Voraussetzungen, damit das Skript auch mit dem original phpBB.com Paket sowie anderen (nationalen) Sprachpaketen verwendet werden kann, da dann jedes nationale Support Forum angepasste Regeln verwenden kann.
* Nachfolgende Ordner wurden zu den externen Ausnahmen hinzugefügt:
  * `ext/phpbb/viglink/language/de/`
  * `ext/phpbb/viglink/language/de_x_sie/`
  * `language/de/`
  * `language/de_x_sie/`
  * `styles/prosilver/theme/de/`
  * `styles/prosilver/theme/de_x_sie/`
* Kleinere Code Verbesserungen.
* PHP Minimum hat sich von 7.0 auf 7.1 geändert.
* Weiteren deaktivierten Code entfernt.

### 0.1.2
(2023-07-02)

* Für VigLink ebenfalls eine interne Ausnahme (`ext/phpbb/viglink/`) definiert. Ist die Ext vorhanden, wird sie auch vollständig geprüft. Ist sie nicht vorhanden, wird sie komplett ignoriert und auch keine MISSINGs mehr ausgegeben.
* In der Zusammenfassung werden die Zahlen jetzt ebenfalls rechtsbündig ausgegeben, wie bei den Zeilennummern.
* Restlichen deaktivierten Code entfernt.
* Kleine Änderungen.

### 0.1.1
(2023-07-01)

* Erste interne Team Version.
