@echo off
title Tracking Posindo - Mode Online & Domain Publik
cd /d "%~dp0"

echo Memulai Tracking Posindo Online...
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0share_tunnel.ps1"

exit /b
