File to Sharepoint
===================================

![](file2sharepoint.svg?raw=true)

Upload local files into sharepoint and print the resulting url to stdout

Requirements
------------

* php 8.1+

Debian/Ubuntu installation
--------------------------

Please use the .deb packages. The repository is availble:

```shell
    echo "deb http://repo.vitexsoftware.com $(lsb_release -sc) main" | sudo tee /etc/apt/sources.list.d/vitexsoftware.list
    sudo wget -O /etc/apt/trusted.gpg.d/vitexsoftware.gpg http://repo.vitexsoftware.com/keyring.gpg
    sudo apt update
    sudo apt install file2sharepoint
```

Usage
-----

file2sharepoint <source/files/path/*.*> <Sharepoint/dest/folder/path/> [/path/to/config/.env]

Configuration
-------------

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
OFFICE365_PATH='Shared documents/files'
```

Into configuration file .env please put ClientID **OR** Login/Password values. 

## Exit Codes

This application uses the following exit codes:

- `0`: Success
- `1`: General error
