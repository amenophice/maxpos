# MaXSync — Agent de sincronizare Saga &harr; MaXPos

Serviciu Windows scris in .NET 9 care ruleaza pe PC-ul clientului unde este
instalat Saga si sincronizeaza datele cu API-ul MaXPos.

## Ce face

- **Articole (la fiecare 30 minute, configurabil)** — citeste articolele active,
  grupele si gestiunile din baza Firebird a Saga si le trimite catre
  `POST /api/v1/sync/articles`.
- **Bonuri (la fiecare 5 minute, configurabil)** — descarca bonurile in
  asteptare de la `GET /api/v1/sync/receipts/pending`, le insereaza in tabelele
  `IESIRI` si `IES_DET` din Saga (intr-o tranzactie), apoi confirma fiecare bon
  cu `POST /api/v1/sync/receipts/{id}/mark-synced`.
- **Reziliem la erori** — orice eroare e logata si bucla continua; serviciul
  nu cade.
- **Re-autentificare automata** — la 401 reia login-ul si reincearca cererea.

## Cerinte

- Windows 10/11 sau Windows Server 2019+
- [.NET 9 Runtime (Windows)](https://dotnet.microsoft.com/download/dotnet/9.0)
- Firebird Client (instalat impreuna cu Saga); conexiunea se face la baza
  `cont_baza.fdb` cu `Charset=WIN1252`.
- Acces de retea catre `MaxPos:BaseUrl`.

## Instalare (compilare locala)

```powershell
cd MaXSync\MaXSync
dotnet publish -c Release
```

## Configurare

Copiaza `appsettings.example.json` peste `appsettings.json` si completeaza:

- `MaxPos.BaseUrl` — URL-ul API-ului MaXPos (ex.: `https://api.maxpos.ro`).
- `MaxPos.Email` / `MaxPos.Password` — credentialele contului dedicat sync.
- `MaxPos.ArticleSyncIntervalMinutes` (implicit 30) si
  `MaxPos.ReceiptExportIntervalMinutes` (implicit 5).
- `Firebird.Database` — calea catre `cont_baza.fdb` (ex.:
  `C:\Saga\cont_baza.fdb`).
- `Firebird.Username` / `Firebird.Password` — credentiale Firebird.
- `Firebird.Charset` — lasa `WIN1252` (Saga foloseste codificare romaneasca
  pe Windows-1252).

## Instalare ca serviciu Windows

Ruleaza din PowerShell **ca administrator**:

```powershell
cd MaXSync\MaXSync
powershell -ExecutionPolicy Bypass -File install-service.ps1
```

Scriptul creeaza serviciul `MaXSync` cu pornire automata si il porneste.

## Loguri

Logurile se scriu in folderul `logs/` langa executabil, rulate zilnic
(`maxsync-YYYY-MM-DD.log`), pastrate 30 de zile. Logurile sunt scrise si in
consola atunci cand serviciul ruleaza in prim-plan.

Verificare rapida din PowerShell:

```powershell
Get-Service MaXSync
Get-Content .\bin\Release\net9.0-windows\logs\maxsync-*.log -Tail 50 -Wait
```

## Stare locala (`state.json`)

Langa executabil este creat `state.json` cu ultima ora a sincronizarii
articolelor si a exportului bonurilor. Serviciul nu depinde de el pentru
corectitudine — este folosit doar pentru telemetrie locala.

## Dezinstalare

```powershell
cd MaXSync\MaXSync
powershell -ExecutionPolicy Bypass -File uninstall-service.ps1
```

## Note despre schema Saga (v602)

- Tabele citite: `ARTICOLE`, `ART_GR`, `GESTIUNI`.
- Tabele scrise (la export bon): `IESIRI`, `IES_DET`.
- Generatoare folosite (verifica numele exact in instalarea ta):
  - `GEN_IESIRI_ID` — pentru `IESIRI.ID_IESIRE`.
  - `GEN_IES_DET_PK` — pentru `IES_DET.PK`.

  Daca numele difera, modifica `Services/FirebirdService.cs` (cauta `TODO`).
