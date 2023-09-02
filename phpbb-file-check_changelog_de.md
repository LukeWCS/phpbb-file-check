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
