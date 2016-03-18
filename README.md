# Herbie Ress Plugin

`Ress` ist ein [Herbie](http://github.com/getherbie/herbie) Plugin, mit dessen Hilfe die Ausgabe des Servers an das 
Gerät angepasst werden kann, mit dem die Website aktuell besucht wird. Es stellt eine Variable "vw" bereit, die die aktuell
gültige Midestbreite der Anzeige enthält (ermittelt anhand der konfigurierten Breakpoints).

Die Idee ist, den Browser mit Hilfe des "srcset"-Attributs die für die aktuelle Anzeige geeignetste Bildbreite aussuchen 
zu lassen, das Bild von PHP generieren zu lassen und die nachgefragte Breite in einer Session-Variable auf dem Server zu speichern.
Mit Hilfe von Twig kann dann das Layout entsprechend der Breite angepasst und nicht benötigte Assets gar nicht erst auusgeliefert 
zu werden.

Ist die Seiteneigenschaft "ress: true" gesetzt, werden alle in der Seite vorhandenen Bilder mit Hilfe des Imagine-Plugins auf die
Breite skaliert, die genäß den entsprechenden ress-Filter vorgegeben ist (s.u.). Zusätzlich kann allen Bildern noch eine css-Eigenschaft übergeben werden.

ACHTUNG:  


## Installation

Das Plugin installierst du, in dem Du es in den Plugin-Ordner kopierst.

Danach aktivierst Du das Plugin in der Konfigurationsdatei.

    plugins:
        enable:
            - ress


## Konfiguration

Das Plugin muss konfiguriert werden. Die Einstellungen unter `plugins/ress/blueprint.yml` dienen als Vorlage. Füge diese unter `plugins.config.ress` ein:

    plugins:
        config:
            ress:
                vw: [100,200,300,400,500,600,700,768,1024,2048]
                test: true
                info: true
                cssWidth: 'max-width:100%;'
                cssHeight: 'height: auto;'
            
            imagine:
                filter_sets:
                    ress1024:
                        test: false
                        filters:
                            thumbnail:
                                size:
                                    - 1024
                                    - 5012
                                mode: inset
                        ress2048:
                            test: false
                                filters:
                                thumbnail:
                                    size:
                                        - 2048
                                        - 1024
                                    mode: inset
                        ressMax:
                            test: false
                            filters:
                                thumbnail:
                                    size:
                                        - 2048
                                        - 1024
                                    mode: inset
