### 1.0.0
(12.7.2023)

* Erste öffentliche Version.
* Ausser Versionsänderung keinen Unterschied zu 0.5.1.

### 0.5.1
(11.7.2023)

* Die Zeile mit der Variable für den Debug-Modus ist kein Kommentar mehr, sondern eine aktive Codezeile. Die Variable ist per Standard mit `false` belegt und kann bei Bedarf auf `true` gesetzt werden.
* Nachfolgende Ordner wurden zu den Ausnahmen hinzugefügt:
  * `docs/`
* Auch Fehlermeldungen die einen Abbruch von FC zur Folge haben, zum Beispiel bei einer fehlenden Prüfsummen-Datei, sind jetzt in ein HTML Gerüst eingebettet, sofern FC im Browser ausgeführt wird.
* Ausserdem wird jetzt zumindest der FC Titel immer ausgegeben, also auch bei Abbruch.

### 0.5.0
(10.7.2023)

* Da ich festgestellt habe, dass in den phpBB Paketen einige überflüssige Dateien vorhanden sind, mussten weitere Einträge zur Ignorieren-Liste hinzugefügt werden. Bei den besagten Dateien handelt es sich um git Dateien, die in einem Distributionspaket für Endbenutzer eigentlich nichts zu suchen haben. Damit diese Dateien nicht umständlich einzeln hinzugefügt werden müssen, was auch nicht zukunftssicher wäre, wurde die Ignorieren-Funktion von einem simplen Textvergleich auf RegEx umgestellt. Dadurch ist die Ignorieren-Funktion nun erheblich flexibler verwendbar.
* Um genau verfolgen zu können, welche Dateien von FC ignoriert und welche per Ausnahme ausgeschlossen wurden, kann man jetzt bei Bedarf den neuen Debug-Modus aktivieren. Dazu muss im FC Skript einfach die Zeile `$debug_mode = true` aktiviert werden. Dadurch werden folgende zusätzliche Anzeigen generiert:
  * Im Titel der Zusatz `(DEBUG MODE)`.
  * In der Meldungsliste bei Ausschlüssen explizit ob die Regel Ignoriert (`- IGNORED`) oder Ausnahme (`- EXCEPTION`) angewendet wurde.
  * In der Zusammenfassung 2 neue Zähler für Ignorierte und Ausnahmen.
* RegEx für die Plausibilitätsprüfung der MD5 Einträge sowie der phpBB Version in der MD5 Datei strikter gestaltet.
* Mehrere Texte und auch Code neutral gestaltet in Bezug auf den verwendeten Hash Algorithmus. Somit ist ein zukünftiger Wechsel auf einen anderen Hash Algorithmus einfacher.
* Kleine Code Verbesserungen.

### 0.4.0
(8.7.2023)

* Bei Dateizugriffen werden statt relative Pfade jetzt absolute Pfade verwendet. In Meldungen werden nach wie vor relative Pfade angezeigt.
* Die Einleitung "Please wait, we are checking x files..." war nicht korrekt, da es von den gültigen MD5 Einträgen sowie von den Listen der Ignorierten und Ausnahmen abhängt, wieviele Dateien tatsächlich geprüft werden. Darum Einleitung geändert in "Please wait, x MD5 entries are being processed...".
* Neue Information in der Zusammenfassung: Wieviele gültige MD5 Einträge effektiv vorhanden sind. Nur MD5 Einträge, die die Plausibilitätsprüfung bestehen, werden als gültige Einträge gezählt.
* Neue Information in der Zusammenfassung: Wieviele Dateien effektiv überprüft wurden. Hier sind die Ignorierten und Ausnahmen also abgezogen.
* Damit Admins nicht durch die Anzeige "ERRORS total" in der Zusammenfassung irritiert werden, wird diese Zeile jetzt nur noch ausgegeben, wenn auch tatsächlich ein FC Fehler vorliegt.
* Die letzte Zeile in der Prüfsummen-Datei, in der die phpBB Version vorhanden sein muss, wird jetzt auf Plausibilität geprüft. Handelt es sich nicht um eine gültige Version mit dem Muster "x.y.z", wird bei der MD5 Version "{unknown}" angezeigt. Des Weiteren wird diese Zeile bei einem Versionsfehler auch nicht aus der MD5 Liste entfernt, wodurch die fehlerhafte Zeile automatisch durch die MD5 Plausibilitätsprüfung gemeldet wird.
* Kleine Code Verbesserungen.

### 0.3.0
(6.7.2023)

* Ab dieser Version kann FC selbständig die richtige MD5 laden, sofern vorhanden. Dazu muss die passende `filecheck_x.y.z.md5` vorhanden sein. Dieser Modus wird dann durch den Zusatz "(auto)" signalisiert. 
* Der automatische Modus kann jederzeit mittels `filecheck.md5` übersteuert werden. In diesem Fall wird der manuelle Modus aktiviert und alle anderen `filecheck_x.y.z.md5` Dateien werden ignoriert. Dieser Modus wird dann durch den Zusatz "(manually)" signalisiert. Das kann in seltenen Fällen notwendig sein, wenn FC die phpBB Version nicht ermitteln kann.
* FC und MD5 Dateien sind jetzt in getrennten Archiven organisiert, so wie es später dann auch bei Veröffentlichung auf phpBB.de der Fall sein wird.
* Bei Verwendung im Browser wurde bisher noch der alte Seitentitel "phpBB CheckFiles" verwendet. Jetzt wird der korrekte Titel angezeigt.
* Code Optimierung.
* Kleinkram.
* Liste der Eigenschaften und Anleitung aktualisiert.

### 0.2.1
(3.7.2023)

* Fix: Bei PHP <8.0 wurde "Uncaught Error: Call to undefined function str_starts_with()" erzeugt. [Meldung von Scanialady]

### 0.2.0
(3.7.2023)

* Fix: Bei fehlender `constants.php` wurde "Warning: Undefined variable" erzeugt. Im Prototyp (0.0.x) war das bereits berücksichtigt, jedoch nicht mehr ab 0.1.0.
* Umbenannt von "phpBB CheckFiles" zu "phpBB File Check". Das betrifft auch die Dateinamen und die Anleitung.
* Die Zeilen des Berichts werden nicht mehr einzeln ausgegeben, sondern in einem Puffer gesammelt und dann blockweise ausgegeben. Das kann bei sehr vielen Abweichungen noch ein bisschen Zeit einsparen.
* Die Anzahl Zeichen für rechtsbündig ausgerichtete Zahlen ist nicht mehr auf 4 fixiert, sondern wird dynamisch anhand der Anzahl Zeilen in der Prüfsummen-Datei ermittelt.
* Die Liste der Fehlermeldungen ist jetzt in 2 Bindestrich-Linien eingefasst.
* Es gibt jetzt die Möglichkeit, Ignorierte und Ausnahmen in einer externen Datei definieren zu können, ohne das Skript ändern zu müssen: `filecheck_ignore.txt` und `filecheck_exceptions.txt`. Das ist eine der Voraussetzungen, damit das Skript auch mit dem original phpBB.com Paket sowie anderen (nationalen) Sprachpaketen verwendet werden kann, da dann jedes nationale Support Forum angepasste Regeln verwenden kann.
* Nachfolgende Ordner wurden zu den Ausnahmen hinzugefügt:
  * `ext/phpbb/viglink/language/de/`
  * `ext/phpbb/viglink/language/de_x_sie/`
  * `styles/prosilver/theme/de/`
  * `styles/prosilver/theme/de_x_sie/`
* Kleinere Code Verbesserungen.
* PHP Minimum hat sich von 7.0 auf 7.1 geändert.
* Weiteren deaktivierten Code entfernt.

### 0.1.2
(2.7.2023)

* Für VigLink ebenfalls eine Ordner Ausnahme definiert. Ist die Ext vorhanden, wird sie auch vollständig geprüft. Ist sie nicht vorhanden, wird sie komplett ignoriert und auch keine MISSINGs mehr ausgegeben.
* In der Zusammenfassung werden die Zahlen jetzt ebenfalls rechtsbündig ausgegeben, wie bei den Zeilennummern.
* Restlichen deaktivierten Code entfernt.
* Kleinkram.

### 0.1.1
(1.7.2023)

* Erste interne Team Version.
