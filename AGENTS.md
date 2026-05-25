# AGENTS.md - Working AI Reference for file2sharepoint

## Project Overview
**Type**: PHP CLI tool / Debian Package  
**Purpose**: Upload local files to SharePoint and print the resulting URL to stdout  
**Status**: Active  
**Repository**: git@github.com:VitexSoftware/file2sharepoint.git  

## Key Technologies
- PHP 8.1+
- [vgrem/php-spo](https://github.com/vgrem/PHPSharePoint) — SharePoint REST API client
- [vitexsoftware/ease-core](https://github.com/VitexSoftware/php-ease-core) — config/logging framework
- Composer
- Debian Packaging (dh-phpcomposer)

## Architecture & Structure
```
file2sharepoint/
├── src/
│   └── file2sharepoint.php   # Main CLI script (procedural)
├── bin/
│   └── file2sharepoint       # Shell wrapper installed to /usr/bin/
├── tests/
│   └── File2SharepointTest.php
├── multiflexi/
│   └── file2sharepoint.app.json  # MultiFlexi app descriptor
├── debian/
│   ├── autoload.php          # Debian-specific PSR-4 autoloader
│   ├── rules                 # Debian build rules (patches require path via sed)
│   ├── changelog             # Version history
│   └── ...
├── .env.example              # Environment variable template
├── phpunit.xml.dist
└── phpstan-default.neon.dist
```

## Configuration (Environment Variables)

| Variable | Required | Description |
|---|---|---|
| `OFFICE365_TENANT` | Yes | Tenant name (e.g. `yourcompany` for `yourcompany.sharepoint.com`) |
| `OFFICE365_SITE` | Yes | SharePoint site name |
| `SHAREPOINT_LIBRARY` | No | Destination folder path (defaults to `$argv[2]`) |
| `OFFICE365_USERNAME` | No* | User login (user-auth mode) |
| `OFFICE365_PASSWORD` | No* | User password (user-auth mode) |
| `OFFICE365_CLIENTID` | No* | App client ID (client-credentials mode) |
| `OFFICE365_CLSECRET` | No* | App client secret (client-credentials mode) |

*Either USERNAME+PASSWORD **or** CLIENTID+CLSECRET must be provided.

## Usage
```
file2sharepoint <source/files/path/*.*> <SharePoint/dest/folder/> [/path/to/.env]
```

## Development Workflow

### Setup
```bash
git clone git@github.com:VitexSoftware/file2sharepoint.git
cd file2sharepoint
composer install
cp .env.example .env   # fill in credentials
```

### Run
```bash
# Run from src/ so the relative ../vendor/autoload.php resolves correctly
cd src && php file2sharepoint.php 'path/to/files/*' 'Documents/uploads'
```

### Tests & Static Analysis
```bash
make tests                    # PHPUnit
make static-code-analysis     # PHPStan level 6
make cs                       # PHP CS Fixer (auto-fix)
```

### Debian Package Build
```bash
dpkg-buildpackage -b -uc
```
`debian/rules` patches the `require_once '../vendor/autoload.php'` line in the
installed script to use `/usr/share/php/file2sharepoint/` (the Debian autoloader).

## Key Invariants
- `require_once '../vendor/autoload.php'` is a **relative path from `src/`** — always run the script with CWD = `src/` in development, or via the installed `/usr/bin/file2sharepoint` wrapper in production.
- The `debian/autoload.php` bridges the Debian system PHP packages; it is installed to `/usr/lib/file2sharepoint/autoload.php` and activated by the `debian/rules` sed substitution at package build time.
- Authentication is either **user credentials** (USERNAME + PASSWORD) or **client credentials** (CLIENTID + CLSECRET). The code checks USERNAME first and falls through to client credentials.
- Exit code `0` = success (URLs printed to stdout). Exit code `1` = any error (message on stderr).
