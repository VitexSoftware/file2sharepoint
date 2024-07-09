Raiffeisenbank for Stormware Pohoda
===================================

![](pohoda-raiffeisenbank.svg?raw=true)

Downloads bank statements in PDF and XML formats.
The XML is parsed and imported into Pohoda via the mServer service.
The PDF is sent to a sharepoint folder and the link to download it is 
attached via MSSQL to all bank statements from the XML.

Requirements
------------

* php 8.1+
* Pohoda (Pohoda SQL for full functionality) + [mServer](https://www.stormware.cz/pohoda/xml/mserver/)
* Sharepoint User or Application Account
* MSSQL login and password
* [php-sqlsrv](https://learn.microsoft.com/en-us/sql/connect/php/microsoft-php-driver-for-sql-server?view=sql-server-ver16)

Debian/Ubuntu installation
--------------------------

Please use the .deb packages. The repository is availble:

 ```shell
    echo "deb http://repo.vitexsoftware.com $(lsb_release -sc) main" | sudo tee /etc/apt/sources.list.d/vitexsoftware.list
    sudo wget -O /etc/apt/trusted.gpg.d/vitexsoftware.gpg http://repo.vitexsoftware.com/keyring.gpg
    sudo apt update
    sudo apt install pohoda-raiffeisenbank
```

Po instalaci balíku jsou v systému k dispozici tyto nové příkazy:

  * **abraflexi-raiffeisenbank-setup**         - check and/or prepare Bank account setup in AbraFlexi
  * **abraflexi-raiffeisenbank-transactions**  - Import transactions. From latest imported or within the given scope
  * **abraflexi-raiffeisenbank-statements**    - Import transactions from Account Statements.
  * **abraflexi-raiffeisenbank-xml-statement** - Import transactions from XML Statements file.

Configuration
-------------

```env
EASE_LOGGER=syslog|console
CERT_FILE='RAIFF_CERT.p12'
CERT_PASS=CertPass
XIBMCLIENTID=PwX4bOQLWGiuoErv6I
ACCOUNT_NUMBER=666666666
ACCOUNT_CURRENCY=CZK

STATEMENT_IMPORT_SCOPE=last_two_months
TRANSACTION_IMPORT_SCOPE=yesterday

API_DEBUG=True
APP_DEBUG=True
STATEMENT_LINE=ADDITIONAL

POHODA_ICO=12345678
POHODA_URL=http://10.11.25.25:10010
POHODA_USERNAME=mServerXXX
POHODA_PASSWORD=mServerXXX
POHODA_TIMEOUT=60
POHODA_COMPRESS=false
POHODA_DEBUG=true
POHODA_BANK_IDS=RB

DB_CONNECTION=sqlsrv
DB_HOST=192.168.25.23
DB_PORT=1433
DB_DATABASE=StwPh_12345678_2023
DB_USERNAME=pohodaSQLuser
DB_PASSWORD=pohodaSQLpassword
DB_SETTINGS=encrypt=false
```

Sharepoint Integration
----------------------

Login based auth

```env
OFFICE365_USERNAME=me@company.tld
OFFICE365_PASSWORD=xxxxxxxxxxxxxx
```

ClientID based auth

```env
OFFICE365_CLIENTID=78842b49-651d-516e-0f2g-f979956aa620
OFFICE365_SECRET=09f04vbd-cfbc-5d78-afb7-2dfbebc4c385
OFFICE365_CLSECRET=8FR8Q~3Rab4-5o8dVd~1vDRId9oYiqEtMJB.Ucb2
```

Destination options

```env
OFFICE365_TENANT=yourcomapny
OFFICE365_SITE=YourSite
OFFICE365_PATH='Shared documents/statements'
```

Into configuration file .env please put ClientID **OR** Login/Password values. 

Powered by
----------

* https://github.com/VitexSoftware/php-vitexsoftware-rbczpremiumapi
* https://github.com/Spoje-NET/PohodaSQL
* https://github.com/VitexSoftware/PHP-Pohoda-Connector

MultiFlexi
----------

Pohoda RaiffeisenBank is ready for run as [MultiFlexi](https://multiflexi.eu) application.
See the full list of ready-to-run applications within the MultiFlexi platform on the [application list page](https://www.multiflexi.eu/apps.php).

[![MultiFlexi App](https://github.com/VitexSoftware/MultiFlexi/blob/main/doc/multiflexi-app.svg)](https://www.multiflexi.eu/apps.php)
