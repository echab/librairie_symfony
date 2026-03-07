@echo off
@setlocal

@REM For installation from entreprise network, with entreprise certificates. Export the crt file from https://packagist.org/ using Chrome site info.
@REM set CA_FILE=%USERPROFILE%/.ssh/repo.packagist.org.crt

if not "!CA_FILE!"=="" (
    set PHP_SSL=, stream_context_create^(['ssl'=^>['cafile'=^>'!CA_FILE!']]^)
    set COMPOSER_SSL= --cafile=!CA_FILE!
    @REM set PHP_SSL=, stream_context_create(['ssl'=^>['verify_peer'=^>false]])
    @REM set COMPOSER_SSL= --disable-tls
)

@chcp 65001>nul
for /f %%a in ('echo prompt $E^| cmd') do set "ESC=%%a"

set PHP=%~dp0php

echo.%PATH%|findstr /C:"!PHP!" >nul 2>&1
if errorlevel 1 (
    @echo !ESC![0;34m• met à jour PATH!ESC![0m avec !PHP!
    powershell -command ^
    "try { $key = [Microsoft.Win32.Registry]::CurrentUser.OpenSubKey('Environment', $true); " ^
    "$oldPath = $key.GetValue('Path', '', 'DoNotExpandEnvironmentNames').TrimStart([IO.Path]::PathSeparator); " ^
    "if (-not $oldPath.Contains('!PHP!')) { " ^
    "   $newPath = '{0}{1}{2}' -f '!PHP!', [IO.Path]::PathSeparator, $oldPath; " ^
    "   $key.SetValue('Path', $newPath, 'ExpandString') } " ^
    "} finally { if ($null -ne $key) { $key.Dispose() } }"
)

set PHP_EXE=!PHP!\php.exe

if not exist "!PHP_EXE!" (
    @echo !ESC![0;34m• Installe PHP... !ESC![0m

    @REM get zip and sha from https://www.php.net/downloads.php
    @REM set PHP_ZIP=php-8.3.24-nts-Win32-vs16-x64.zip
    @REM set PHP_SHA256=f48d7f9b43197768c81bd4bfea3f5d246aed059e8aeb7f8b9ff9474fa5e9ec12

    set PHP_ZIP=php-8.3.30-nts-Win32-vs16-x64.zip
    set PHP_SHA256=42637b42b38b9c0d731e59c5cb8b755693a01b110cd2f31951f67de5cb4cd129

    @REM set PHP_ZIP=php-8.4.17-nts-Win32-vs17-x64.zip
    @REM set PHP_SHA256=c0dec956a5f1787c42f52ee410bacbf0ef091ca454eb42920ab7aab3d86234b0

    if not exist "!PHP!" ( mkdir "!PHP!" )

    set ZIP=%USERPROFILE%\Downloads\!PHP_ZIP!
    if not exist "!ZIP!" (
        powershell -command "(new-object System.Net.WebClient).DownloadFile('https://downloads.php.net/~windows/releases/!PHP_ZIP!','!ZIP!')"
        if errorlevel 1 pause
    )
    powershell -command "$hash = (Get-FileHash '!ZIP!' -Algorithm 'SHA256').Hash; if ($hash -ne '!PHP_SHA256!') { echo '🔺 !ZIP! corrupt, hash= ' $hash; Remove-Item '!ZIP!'; exit 1; }"
    if errorlevel 1 goto :done

    powershell -command "Expand-Archive ^"!ZIP!^" ^"!PHP!^""
    if errorlevel 1 goto :done

    powershell -command "Remove-Item '!ZIP!'"

    if not exist "!PHP!\php.ini" (
        powershell -command ^
        "$ini=[IO.File]::ReadAllText('!PHP!\php.ini-development'); " ^
        "$ini=$ini -replace '; ?(extension_dir ?= ?""ext"")','$1'; " ^
        "$ini=$ini -replace '; *(extension ?= ?openssl)','$1'; " ^
        "$ini=$ini -replace '; *(extension ?= ?curl)','$1'; " ^
        "$ini=$ini -replace '; *(extension ?= ?fileinfo)','$1'; " ^
        "$ini=$ini -replace '; *(extension ?= ?gd)','$1'; " ^
        "$ini=$ini -replace '; *(extension ?= ?intl)','$1'; " ^
        "$ini=$ini -replace '; *(extension ?= ?mbstring)','$1'; " ^
        "$ini=$ini -replace '; *(extension ?= ?exif)','$1'; " ^
        "$ini=$ini -replace '; *(extension ?= ?zip)','$1'; " ^
        "$ini=$ini -replace ';? *(date.timezone ?=)','date.timezone = Europe/Paris'; " ^
        "if ('!CA_FILE!' -ne '') { $ini=$ini -replace '; *(openssl.cafile) ?=','$1=!CA_FILE!'; } " ^
        "[IO.File]::WriteAllText('!PHP!\php.ini', $ini); "
        if errorlevel 1 goto :done
    )
)
@echo !ESC![0;32m• ✅ PHP !ESC![0m
@REM "!PHP_EXE!" --version


@REM xdebug
if not exist "!PHP!\ext\php_xdebug.dll" (
    @echo !ESC![0;34m• Installe PHP XDebug... !ESC![0m
    @REM https://xdebug.org/wizard
    @REM https://github.com/xdebug/xdebug/releases

    set XDEBUG_VERSION=php_xdebug-3.4.5-8.3-nts-vs16-x86_64.dll
    set XDEBUG_SHA256=0800BAE0FB4740E85A2B58CA795D7098AABD8E5FF051EF9440E934FC18624787

    @REM set XDEBUG_VERSION=php_xdebug-3.5.0-8.4-nts-vs17-x86_64.dll
    @REM set XDEBUG_SHA256=850b00be47783b72c221be408a6ea6665820a863ceea3d84d53e7a84ecc667f0

    @REM https://xdebug.org/files/php_xdebug-3.4.5-8.3-nts-vs16-x86_64.dll

    if not exist "!PHP!\ext" mkdir "!PHP!\ext"
    set XDEBUG_DLL=!PHP!\ext\php_xdebug.dll
    "!PHP_EXE!" -r "copy('https://xdebug.org/files/!XDEBUG_VERSION!', '!XDEBUG_DLL!' !PHP_SSL!);"
    if errorlevel 1 goto :done

    powershell -command "$hash = (Get-FileHash '!XDEBUG_DLL!' -Algorithm 'SHA256').Hash; if ($hash -ne '!XDEBUG_SHA256!') { echo '🔺 !XDEBUG_DLL! corrupt, hash= ' $hash; Remove-Item '!XDEBUG_DLL!'; exit 1; }"
    if errorlevel 1 goto :done

    echo.>>                                 "!PHP!\php.ini"
    echo.zend_extension = xdebug >>         "!PHP!\php.ini"
    echo.xdebug.start_with_request = yes >> "!PHP!\php.ini"
    echo.; xdebug.start_upon_error = yes >> "!PHP!\php.ini"
    echo.xdebug.client_host=127.0.0.1 >>    "!PHP!\php.ini"
    echo.xdebug.client_port=9003 >>         "!PHP!\php.ini"
    echo.xdebug.remote_handler=dbgp >>      "!PHP!\php.ini"
)
@echo !ESC![0;32m• ✅ PHP XDebug!ESC![0m


@REM Composer
if not exist "!PHP!\composer.phar" (
    @echo !ESC![0;34m• Installe Composer... !ESC![0m

    @REM get SHA from https://getcomposer.org/download/
    set COMPOSER_SHA384=c8b085408188070d5f52bcfe4ecfbee5f727afa458b2573b8eaaf77b3419b0bf2768dc67c86944da1544f06fa544fd47

    "!PHP_EXE!" -r "copy('https://getcomposer.org/installer', '!PHP!\composer-setup.php' !PHP_SSL!);"
    if errorlevel 1 goto :done
    "!PHP_EXE!" -r "$hash = hash_file('sha384', '!PHP!\composer-setup.php'); if ($hash ^!== '!COMPOSER_SHA384!') { echo '🔺 Composer installer corrupt, hash= '. $hash . PHP_EOL; unlink('!PHP!\composer-setup.php'); exit(1); }"
    if errorlevel 1 goto :done
    pushd "!PHP!"
    php composer-setup.php !COMPOSER_SSL!
    if errorlevel 1 ( popd & goto :done )
    popd
    "!PHP_EXE!" -r "unlink('!PHP!\composer-setup.php');"
    if errorlevel 1 goto :done
)

if not exist "!PHP!\composer.cmd" (
    echo.#^^!/usr/bin/env php 2^>nul > "!PHP!\composer.cmd"
    echo.: ^<?php /* >> "!PHP!\composer.cmd"
    echo.^"%%~dp0php.exe^" ^"%%~dp0composer.phar^" %%* >> "!PHP!\composer.cmd"
    echo.goto :eof >> "!PHP!\composer.cmd"
    echo.@REM */ >> "!PHP!\composer.cmd"
    echo.require '^%%~dp0composer.phar'; >> "!PHP!\composer.cmd"
)

@echo !ESC![0;32m• ✅ Composer !ESC![0m
@REM call "!PHP!\composer" --version


@REM Symfony CLI

if not exist "!PHP!\symfony.exe" (
    @echo !ESC![0;34m• Installe Symfony CLI... !ESC![0m

    set SYMFONY_VERSION=5.12.0
    set SYMFONY_SHA256=0dd3f708f5fc276dd2a493520adc672c17eabb873c879285578595a8f3ad88d1

    set ZIP=%USERPROFILE%\Downloads\symfony-cli_windows_386.zip
    "!PHP_EXE!" -r "copy('https://github.com/symfony-cli/symfony-cli/releases/download/v!SYMFONY_VERSION!/symfony-cli_windows_386.zip', '!ZIP!' !PHP_SSL!);"
    if errorlevel 1 goto :done

    "!PHP_EXE!" -r "$hash = hash_file('sha256', '!ZIP!'); if ($hash ^!== '!SYMFONY_SHA256!') { echo 'Symfony installer corrupt hash= '. $hash .PHP_EOL; unlink('!ZIP!'); exit(1); }"
    if errorlevel 1 goto :done

    @REM "!PHP_EXE!" -r "$z=new ZipArchive(); $z->open('!ZIP!', ZipArchive::RDONLY); file_put_contents('!PHP!\symfony.exe', $z->getFromName('symfony.exe')); $z->close(); unlink('!ZIP!');"
    "!PHP_EXE!" -r "$z=new ZipArchive(); $z->open('!ZIP!', ZipArchive::RDONLY); $z->extractTo('!PHP!'); $z->close(); unlink('!ZIP!');"
    if errorlevel 1 goto :done
)
@echo !ESC![0;32m• ✅ Symfony CLI !ESC![0m
@REM "!PHP!\symfony" -V


@REM VSCode
if not exist "%~dp0VSCode\code.exe" (
    @REM https://code.visualstudio.com/download
    @echo !ESC![0;34m• Installe Visual Studio Code... !ESC![0m

    set VSCODE_VERSION=1.103.1
    set VSCODE_SHA256=3c1ce7363180fa496614c00d5101fe785ce7c8af928233320283989dc17be5dd

    if not exist "%~dp0VSCode" mkdir "%~dp0VSCode"
    set ZIP=%USERPROFILE%\Downloads\VSCode-win32-x64-!VSCODE_VERSION!.zip
    @REM "!PHP_EXE!" -r "copy('https://code.visualstudio.com/sha/download?build=stable&os=win32-x64-archive', '!ZIP!' !PHP_SSL!);"
    if not exist "!ZIP!" (
        "!PHP_EXE!" -r "copy('https://update.code.visualstudio.com/!VSCODE_VERSION!/win32-x64-archive/stable', '!ZIP!' !PHP_SSL!);"
        if errorlevel 1 goto :done
    )

    "!PHP_EXE!" -r "$hash = hash_file('sha256', '!ZIP!'); if ($hash ^!== '!VSCODE_SHA256!') { echo 'Visual Studio Code installer corrupt hash='. $hash .PHP_EOL; unlink('!ZIP!'); exit(1); }"
    if errorlevel 1 goto :done

    "!PHP_EXE!" -r "$z=new ZipArchive(); $z->open('!ZIP!', ZipArchive::RDONLY); $z->extractTo('%~dp0VSCode/'); $z->close(); unlink('!ZIP!');"
    if errorlevel 1 goto :done

    @REM powershell -command "Unblock-File '%~dp0VSCode\code.exe'"

    @REM @echo !ESC![0;34m• Installe Visual Studio Code extensions... !ESC![0m
    @REM call "%~dp0VSCode\code.exe" --install-extension devsense.phptools-vscode
    @REM call "%~dp0VSCode\code.exe" --install-extension redhat.vscode-yaml
    @REM call "%~dp0VSCode\code.exe" --install-extension xdebug.php-debug
    @REM call "%~dp0VSCode\code.exe" --install-extension actboy168.tasks
    @REM call "%~dp0VSCode\code.exe" --install-extension mblode.twig-language-2
    @REM call "%~dp0VSCode\code.exe" --install-extension xdebug.php-debug
)
@echo !ESC![0;32m• ✅ Visual Studio Code !ESC![0m


@echo !ESC![0;32m• Tous les outils sont installés :-) !ESC![0m
:done
pause