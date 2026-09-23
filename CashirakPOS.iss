; ============================================================
; Cashirak POS — Inno Setup 6 Script
; Developer: Elmahdi Dev  |  https://almhdy24.com
;
; Build command (from project root, Windows):
;   iscc CashirakPOS.iss
;
; Output: Output\CashirakPOS-Setup.exe
; ============================================================

#define AppName        "Cashirak POS"
#define AppVersion     "1.0.0"
#define AppPublisher   "Elmahdi Dev"
#define AppURL         "https://almhdy24.com"
#define AppSupportURL  "https://almhdy24.com"
#define AppExeName     "LaunchCashirakPOS.vbs"
#define AppDataFolder  "Cashirak POS"
#define CopyrightYear  "2026"

[Setup]
; Stable GUID — never change this after first release or upgrades will break
AppId={{7A3F8B2E-C4D1-4E9A-B6F0-5D2E1A7C8B3F}
AppName={#AppName}
AppVersion={#AppVersion}
AppVerName={#AppName} v{#AppVersion}
AppPublisher={#AppPublisher}
AppPublisherURL={#AppURL}
AppSupportURL={#AppSupportURL}
AppUpdatesURL={#AppURL}

; Windows 10 minimum (required for bundled 64-bit PHP 8.x)
MinVersion=10.0

; Install to Program Files\Cashirak POS
DefaultDirName={autopf}\{#AppName}
DefaultGroupName={#AppName}
AllowNoIcons=yes

; Wizard appearance (IS6 supports PNG natively)
WizardStyle=modern
WizardResizable=no
WizardImageFile=installer_assets\wizard_banner.png
WizardSmallImageFile=installer_assets\wizard_small.png

; Installer icon
SetupIconFile=CashirakPOS.ico
UninstallDisplayIcon={app}\CashirakPOS.ico

; Output
OutputDir=Output
OutputBaseFilename=CashirakPOS-Setup
Compression=lzma2/ultra64
SolidCompression=yes

; Admin required — writes to Program Files and ProgramData
PrivilegesRequired=admin

; 64-bit only — bundled PHP is x64
ArchitecturesAllowed=x64compatible
ArchitecturesInstallIn64BitMode=x64compatible

; Version info embedded in the .exe PE header
VersionInfoVersion={#AppVersion}.0
VersionInfoProductVersion={#AppVersion}.0
VersionInfoCompany={#AppPublisher}
VersionInfoDescription={#AppName} — نظام نقاط البيع
VersionInfoCopyright=Copyright © {#CopyrightYear} {#AppPublisher}
VersionInfoProductName={#AppName}

LicenseFile=
InfoBeforeFile=
InfoAfterFile=

[Languages]
Name: "english"; MessagesFile: "compiler:Default.isl"

[Tasks]
Name: "desktopicon"; Description: "{cm:CreateDesktopIcon}"; GroupDescription: "{cm:AdditionalIcons}"

[Dirs]
; ProgramData directories — created once, NEVER removed on uninstall (user data)
Name: "{commonappdata}\{#AppDataFolder}";                  Permissions: users-modify
Name: "{commonappdata}\{#AppDataFolder}\database";         Permissions: users-modify
Name: "{commonappdata}\{#AppDataFolder}\storage";          Permissions: users-modify
Name: "{commonappdata}\{#AppDataFolder}\storage\logs";     Permissions: users-modify
Name: "{commonappdata}\{#AppDataFolder}\storage\sessions"; Permissions: users-modify
Name: "{commonappdata}\{#AppDataFolder}\storage\backups";  Permissions: users-modify

[Files]
; ── PHP runtime (64-bit, read-only in Program Files) ───────────────────────
Source: "php\*"; DestDir: "{app}\php"; Flags: ignoreversion recursesubdirs createallsubdirs

; ── Application source (read-only in Program Files) ────────────────────────
Source: "app\*";    DestDir: "{app}\app";    Flags: ignoreversion recursesubdirs createallsubdirs
Source: "public\*"; DestDir: "{app}\public"; Flags: ignoreversion recursesubdirs createallsubdirs
Source: "views\*";  DestDir: "{app}\views";  Flags: ignoreversion recursesubdirs createallsubdirs

; ── Root bootstrap & launcher ──────────────────────────────────────────────
Source: "bootstrap.php";          DestDir: "{app}"; Flags: ignoreversion
Source: "start.bat";              DestDir: "{app}"; Flags: ignoreversion
Source: "LaunchCashirakPOS.vbs";  DestDir: "{app}"; Flags: ignoreversion

; ── Certificates (required for license RSA verification) ───────────────────
Source: "certs\*"; DestDir: "{app}\certs"; Flags: ignoreversion recursesubdirs createallsubdirs

; ── Icons ──────────────────────────────────────────────────────────────────
Source: "CashirakPOS.ico"; DestDir: "{app}"; Flags: ignoreversion
Source: "ElmahdiDev.ico";  DestDir: "{app}"; Flags: ignoreversion

; NOTE: storage\* and database\* are intentionally NOT bundled.
; The [Dirs] section creates the writable ProgramData dirs at install time,
; and the [Code] section writes data_path.ini so start.bat finds them.
; Sensitive runtime files (.license_data, .device_id, installed.lock,
; cashirak.sqlite) are never shipped — they are created on first run.

[Icons]
; Start Menu
Name: "{group}\{#AppName}";                                Filename: "wscript.exe"; Parameters: """{app}\{#AppExeName}"""; WorkingDir: "{app}"; IconFilename: "{app}\CashirakPOS.ico"
Name: "{group}\{cm:UninstallProgram,{#AppName}}";          Filename: "{uninstallexe}"

; Desktop (optional task)
Name: "{commondesktop}\{#AppName}";                        Filename: "wscript.exe"; Parameters: """{app}\{#AppExeName}"""; WorkingDir: "{app}"; IconFilename: "{app}\CashirakPOS.ico"; Tasks: desktopicon

[Run]
; Launch Cashirak POS after installation (wscript runs the VBS silently)
Filename: "wscript.exe"; Parameters: """{app}\{#AppExeName}"""; WorkingDir: "{app}"; \
    Description: "{cm:LaunchProgram,{#StringChange(AppName, '&', '&&')}}"; \
    Flags: nowait postinstall skipifsilent

[UninstallDelete]
; data_path.ini is written by [Code], not by [Files], so it won't be
; removed automatically — list it explicitly so uninstall is clean.
Type: files; Name: "{app}\data_path.ini"

[Code]
// ------------------------------------------------------------------
// Write the correct ProgramData path into data_path.ini at install
// time so start.bat can set CASHIRAK_DATA_PATH without any hardcoded
// path. The file is a simple KEY=VALUE ini used by the batch script.
// ------------------------------------------------------------------
procedure CurStepChanged(CurStep: TSetupStep);
var
  DataPath: String;
  IniPath:  String;
begin
  if CurStep = ssPostInstall then
  begin
    DataPath := ExpandConstant('{commonappdata}\{#AppDataFolder}');
    IniPath  := ExpandConstant('{app}\data_path.ini');
    SaveStringToFile(IniPath, 'DATA_PATH=' + DataPath + #13#10, False);
  end;
end;

// ------------------------------------------------------------------
// After uninstall: inform the user that their database and settings
// in ProgramData are preserved and must be deleted manually if wanted.
// ------------------------------------------------------------------
procedure CurUninstallStepChanged(CurUninstallStep: TUninstallStep);
var
  DataPath: String;
begin
  if CurUninstallStep = usPostUninstall then
  begin
    DataPath := ExpandConstant('{commonappdata}\{#AppDataFolder}');
    if DirExists(DataPath) then
      MsgBox(
        'تم إلغاء تثبيت Cashirak POS.' + #13#10 + #13#10 +
        'بياناتك (قاعدة البيانات والإعدادات) محفوظة في:' + #13#10 +
        DataPath + #13#10 + #13#10 +
        'يمكنك حذف هذا المجلد يدوياً إذا أردت إزالة جميع البيانات.',
        mbInformation, MB_OK
      );
  end;
end;
