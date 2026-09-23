Option Explicit

Dim WshShell
Dim FSO
Dim appDir
Dim batFile

Set WshShell = CreateObject("WScript.Shell")
Set FSO = CreateObject("Scripting.FileSystemObject")

' Get application directory
appDir = FSO.GetParentFolderName(WScript.ScriptFullName)

' Get start.bat path
batFile = appDir & "\start.bat"

' Verify start.bat exists
If Not FSO.FileExists(batFile) Then
    MsgBox "Cashirak POS launcher file was not found:" & vbCrLf & _
           batFile, _
           vbCritical, _
           "Cashirak POS"
    WScript.Quit 1
End If

' Run Cashirak POS without showing the CMD window
WshShell.Run """" & batFile & """", 0, False

Set FSO = Nothing
Set WshShell = Nothing
