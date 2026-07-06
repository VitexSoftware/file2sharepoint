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

Setup
-----

Run the interactive wizard to create an Azure App Registration and write a `.env` file:

```shell
    bin/azure-setup-wizard        # writes .env in the project root
    bin/azure-setup-wizard /path/to/.env  # writes to a custom path
    make setup-wizard             # shorthand via Makefile
```

The wizard will:
1. Ask for the SharePoint **tenant**, **site**, and **library/folder**.
2. Let you choose between *App registration* (Client ID + Secret, recommended
   for automation) or *User credentials* (Username + Password).
3. For App registration it optionally uses the [Azure CLI](https://learn.microsoft.com/en-us/cli/azure/)
   to create the app, assign the Microsoft Graph `Sites.Selected` application
   permission, grant the app access to the target site, and generate a
   client secret automatically.
4. Write the resulting credentials to the `.env` file (mode `600`).

> **Required Azure permission for client-credentials mode**  
> Resource: Microsoft Graph (`00000003-0000-0000-c000-000000000000`)  
> Application role: `Sites.Selected`  
> Admin consent must be granted by a Global Administrator, and the app must
> additionally be granted access to the specific site via
> `POST /sites/{siteId}/permissions` (the wizard does this automatically).
>
> Client-credentials auth authenticates via the modern Entra ID v2
> `client_credentials` flow, **not** the legacy SharePoint "App-Only via
> Azure ACS" flow (`AllSites.Write` under the classic SharePoint API
> resource `00000003-0000-0ff1-ce00-000000000000`) — Microsoft fully
> retired ACS for all tenants on 2026-04-02, with no extension possible
> ([details](https://learn.microsoft.com/sharepoint/dev/sp-add-ins/retirement-announcement-for-azure-acs)).
> Confirmed empirically: ACS still issues a syntactically valid token, but
> SharePoint Online now rejects it on the real REST call regardless of
> credential correctness.

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
OFFICE365_CLSECRET=8FR8Q~3Rab4-5o8dVd~1vDRId9oYiqEtMJB.Ucb2
```

Destination options

```env
OFFICE365_TENANT=yourcompany
OFFICE365_SITE=YourSite
SHAREPOINT_LIBRARY='Shared documents/files'
```

Into configuration file .env please put ClientID **OR** Login/Password values. 

## Exit Codes

This application uses the following exit codes:

- `0`: Success
- `1`: General error
