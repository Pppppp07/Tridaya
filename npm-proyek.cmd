@echo off
setlocal
pushd "%~dp0"
if exist "%~dp0.tools\npm\node_modules\npm\bin\npm-cli.js" (
  node "%~dp0.tools\npm\node_modules\npm\bin\npm-cli.js" %*
) else (
  call npm %*
)
set "tridaya_result=%errorlevel%"
popd
exit /b %tridaya_result%
