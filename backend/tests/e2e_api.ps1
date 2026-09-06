[CmdletBinding()]
param(
  [string]$BaseUrl = 'http://localhost/SafeKids-api/api',
  [Parameter(Mandatory = $true)]
  [string]$DbPassword,
  [string]$DbHost = '127.0.0.1',
  [string]$DbUser = 'safekids_api',
  [string]$DbName = 'safekids_v2',
  [string]$MySqlExecutable = 'C:\Program Files\MySQL\MySQL Server 8.0\bin\mysql.exe',
  [string]$PhpExecutable = 'C:\xampp\php\php.exe',
  [switch]$IncludeCloudinary
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.Net.Http

$checks = New-Object System.Collections.Generic.List[object]

function Add-Check {
  param([string]$Name, [string]$Detail = 'OK')

  $checks.Add([pscustomobject]@{
    Prueba = $Name
    Resultado = 'OK'
    Detalle = $Detail
  })
}

function Assert-True {
  param(
    [bool]$Condition,
    [string]$Name,
    [string]$FailureMessage
  )

  if (-not $Condition) {
    throw "$Name`: $FailureMessage"
  }

  Add-Check $Name
}

function Assert-Status {
  param(
    $Response,
    [int[]]$Expected,
    [string]$Name
  )

  if ($Expected -notcontains [int]$Response.StatusCode) {
    $message = if ($null -ne $Response.Data -and $Response.Data.PSObject.Properties['mensaje']) {
      [string]$Response.Data.mensaje
    } else {
      [string]$Response.Content
    }
    throw "$Name`: HTTP $($Response.StatusCode), esperado $($Expected -join '/'). $message"
  }

  Add-Check $Name "HTTP $($Response.StatusCode)"
}

function Invoke-Api {
  param(
    [string]$Method,
    [string]$Endpoint,
    [string]$Token = '',
    $Body = $null,
    [string]$Query = ''
  )

  $uri = "$BaseUrl/$Endpoint"
  if ($Query -ne '') {
    $uri += "?$Query"
  }

  $headers = @{
    Accept = 'application/json'
    'ngrok-skip-browser-warning' = '1'
  }
  if ($Token -ne '') {
    $headers.Authorization = "Bearer $Token"
  }

  $request = @{
    Uri = $uri
    Method = $Method
    Headers = $headers
    UseBasicParsing = $true
  }

  if ($null -ne $Body) {
    $request.ContentType = 'application/json; charset=UTF-8'
    $request.Body = $Body | ConvertTo-Json -Depth 12 -Compress
  }

  try {
    $response = Invoke-WebRequest @request
    $content = [string]$response.Content
    $data = if ([string]::IsNullOrWhiteSpace($content)) {
      $null
    } else {
      $content | ConvertFrom-Json
    }

    return [pscustomobject]@{
      StatusCode = [int]$response.StatusCode
      Data = $data
      Content = $content
    }
  } catch {
    if ($null -eq $_.Exception.Response) {
      throw
    }

    $statusCode = [int]$_.Exception.Response.StatusCode
    $content = ''
    if ($null -ne $_.ErrorDetails) {
      $content = [string]$_.ErrorDetails.Message
    }
    if ([string]::IsNullOrWhiteSpace($content)) {
      $reader = New-Object System.IO.StreamReader($_.Exception.Response.GetResponseStream())
      $content = $reader.ReadToEnd()
      $reader.Dispose()
    }
    $data = if ([string]::IsNullOrWhiteSpace($content)) {
      $null
    } else {
      try { $content | ConvertFrom-Json } catch { $null }
    }

    return [pscustomobject]@{
      StatusCode = $statusCode
      Data = $data
      Content = $content
    }
  }
}

function Invoke-ApiMultipart {
  param(
    [string]$Endpoint,
    [string]$Token,
    [hashtable]$Fields,
    [string]$FilePath,
    [string]$FileField = 'foto',
    [string]$MimeType = 'image/png'
  )

  $client = New-Object System.Net.Http.HttpClient
  $content = New-Object System.Net.Http.MultipartFormDataContent
  try {
    $client.DefaultRequestHeaders.Accept.ParseAdd('application/json')
    if ($Token -ne '') {
      $client.DefaultRequestHeaders.Authorization =
        New-Object System.Net.Http.Headers.AuthenticationHeaderValue('Bearer', $Token)
    }

    foreach ($field in $Fields.GetEnumerator()) {
      $fieldContent = New-Object System.Net.Http.StringContent([string]$field.Value)
      $content.Add($fieldContent, [string]$field.Key)
    }

    $fileBytes = [System.IO.File]::ReadAllBytes($FilePath)
    $fileContent = New-Object System.Net.Http.ByteArrayContent -ArgumentList @(,$fileBytes)
    $fileContent.Headers.ContentType =
      New-Object System.Net.Http.Headers.MediaTypeHeaderValue($MimeType)
    $content.Add($fileContent, $FileField, [System.IO.Path]::GetFileName($FilePath))

    $responseTask = $client.PostAsync("$BaseUrl/$Endpoint", $content)
    $response = $responseTask.GetAwaiter().GetResult()
    $contentTask = $response.Content.ReadAsStringAsync()
    $responseContent = $contentTask.GetAwaiter().GetResult()
    $data = if ([string]::IsNullOrWhiteSpace($responseContent)) {
      $null
    } else {
      try { $responseContent | ConvertFrom-Json } catch { $null }
    }

    return [pscustomobject]@{
      StatusCode = [int]$response.StatusCode
      Data = $data
      Content = $responseContent
    }
  } finally {
    $content.Dispose()
    $client.Dispose()
  }
}

function Invoke-MySql {
  param([string]$Sql)

  $previousPassword = $env:MYSQL_PWD
  $env:MYSQL_PWD = $DbPassword
  try {
    $output = & $MySqlExecutable `
      -N `
      -B `
      -h $DbHost `
      -u $DbUser `
      -D $DbName `
      -e $Sql 2>&1

    if ($LASTEXITCODE -ne 0) {
      throw "MySQL no pudo ejecutar la operacion: $($output -join ' ')"
    }

    return @($output | ForEach-Object { [string]$_ })
  } finally {
    if ($null -eq $previousPassword) {
      Remove-Item Env:MYSQL_PWD -ErrorAction SilentlyContinue
    } else {
      $env:MYSQL_PWD = $previousPassword
    }
  }
}

function Get-MySqlScalar {
  param([string]$Sql)

  $rows = @(Invoke-MySql $Sql)
  if ($rows.Count -eq 0) {
    throw 'MySQL no devolvio el valor esperado.'
  }

  return $rows[$rows.Count - 1].Trim()
}

function Remove-TestDaycare {
  param([int]$DaycareId)

  if ($DaycareId -le 0) {
    return
  }

  $cleanupSql = @"
START TRANSACTION;
DELETE nd FROM notificacion_destinatarios nd
INNER JOIN notificaciones no ON no.id_notificacion = nd.id_notificacion
WHERE no.id_guarderia = $DaycareId;
DELETE FROM notificaciones WHERE id_guarderia = $DaycareId;
DELETE ie FROM imagenes_evento ie
INNER JOIN eventos_nino ev ON ev.id_evento = ie.id_evento
WHERE ev.id_guarderia = $DaycareId;
DELETE FROM eventos_nino WHERE id_guarderia = $DaycareId;
DELETE FROM asistencias WHERE id_guarderia = $DaycareId;
DELETE nt FROM nino_tutor nt
INNER JOIN ninos ni ON ni.id_nino = nt.id_nino
WHERE ni.id_guarderia = $DaycareId;
DELETE nc FROM nino_contacto nc
INNER JOIN ninos ni ON ni.id_nino = nc.id_nino
WHERE ni.id_guarderia = $DaycareId;
DELETE FROM ninos WHERE id_guarderia = $DaycareId;
DELETE FROM contactos_autorizados WHERE id_guarderia = $DaycareId;
DELETE FROM dispositivos_fcm
WHERE id_usuario IN (SELECT id_usuario FROM usuarios WHERE id_guarderia = $DaycareId);
DELETE FROM refresh_tokens
WHERE id_usuario IN (SELECT id_usuario FROM usuarios WHERE id_guarderia = $DaycareId);
DELETE FROM empleados
WHERE id_usuario IN (SELECT id_usuario FROM usuarios WHERE id_guarderia = $DaycareId);
DELETE FROM tutores
WHERE id_usuario IN (SELECT id_usuario FROM usuarios WHERE id_guarderia = $DaycareId);
DELETE FROM bitacora WHERE id_guarderia = $DaycareId;
DELETE FROM usuarios WHERE id_guarderia = $DaycareId;
DELETE FROM guarderias WHERE id_guarderia = $DaycareId;
COMMIT;
"@

  Invoke-MySql $cleanupSql | Out-Null
}

$runId = (Get-Date).ToUniversalTime().ToString('yyyyMMddHHmmss') + (Get-Random -Minimum 100 -Maximum 999)
$daycareId = 0
$daycareEmail = ''
$failure = $null
$uploadedNotificationPublicId = ''
$testImagePath = ''

try {
  $health = Invoke-Api -Method GET -Endpoint 'listar_ninos.php' -Query 'id_guarderia=1'
  Assert-Status $health @(401) 'API exige autenticacion'

  $adminEmail = "e2e.admin.$runId@safekids.test"
  $adminPassword = "E2eAdmin$runId!"
  $adminHash = & $PhpExecutable -r "echo password_hash('$adminPassword', PASSWORD_DEFAULT);"
  $daycareEmail = "e2e.guarderia.$runId@safekids.test"
  $bootstrap = Get-MySqlScalar @"
START TRANSACTION;
INSERT INTO guarderias (nombre,email,telefono,direccion,ciudad,estado_region,codigo_postal)
VALUES ('Guarderia E2E $runId','$daycareEmail','8100000000','Calle de pruebas 100','Monterrey','Nuevo Leon','64000');
SET @daycare_id = LAST_INSERT_ID();
INSERT INTO usuarios (id_guarderia,id_rol,email,password_hash,requiere_cambio_password,estado)
SELECT @daycare_id,id_rol,'$adminEmail','$adminHash',FALSE,'ACTIVO'
FROM roles WHERE nombre='ADMIN';
SET @admin_user_id = LAST_INSERT_ID();
INSERT INTO empleados (id_usuario,nombres,apellidos,telefono,puesto,fecha_ingreso,notas)
VALUES (@admin_user_id,'Admin','E2E','8100000001','Direccion',CURDATE(),'Cuenta temporal de pruebas');
SET @admin_employee_id = LAST_INSERT_ID();
COMMIT;
SELECT CONCAT(@daycare_id,'|',@admin_user_id,'|',@admin_employee_id);
"@
  $bootstrapParts = $bootstrap -split '\|'
  if ($bootstrapParts.Count -ne 3) {
    throw 'No fue posible interpretar los identificadores de la guarderia temporal.'
  }
  $daycareId = [int]$bootstrapParts[0]
  $adminUserId = [int]$bootstrapParts[1]
  $adminEmployeeId = [int]$bootstrapParts[2]
  Assert-True ($daycareId -gt 1) 'Creacion de guarderia aislada' 'No se obtuvo el identificador temporal.'

  $adminLogin = Invoke-Api -Method POST -Endpoint 'login.php' -Body @{
    usuario_login = $adminEmail
    password_login = $adminPassword
    cliente = 'WEB'
  }
  Assert-Status $adminLogin @(200) 'Inicio de sesion del administrador'
  $adminToken = [string]$adminLogin.Data.token

  $wrongAdminClient = Invoke-Api -Method POST -Endpoint 'login.php' -Body @{
    usuario_login = $adminEmail
    password_login = $adminPassword
    cliente = 'ANDROID'
  }
  Assert-Status $wrongAdminClient @(403) 'Administrador rechazado en Android'

  $crossTenantAdmin = Invoke-Api -Method GET -Endpoint 'listar_ninos.php' -Token $adminToken -Query 'id_guarderia=1'
  Assert-Status $crossTenantAdmin @(403) 'Aislamiento de guarderia para administrador'

  $employeeEmail = "e2e.empleado.$runId@safekids.test"
  $employeeCreate = Invoke-Api -Method POST -Endpoint 'registrar_empleado.php' -Token $adminToken -Body @{
    id_guarderia = $daycareId
    id_empleado = $adminEmployeeId
    empleado = @{
      nombres = 'Elena'
      apellidos = 'Pruebas'
      email = $employeeEmail
      telefono = '8100000002'
      puesto = 'Cuidadora'
      fecha_ingreso = (Get-Date).ToString('yyyy-MM-dd')
      rol = 'EMPLEADO'
      notas = 'Registro E2E'
    }
  }
  Assert-Status $employeeCreate @(201) 'Alta de empleado'
  $employeeId = [int]$employeeCreate.Data.id_empleado
  $employeeTempPassword = [string]$employeeCreate.Data.credencial_temporal.password_temporal
  Assert-True (
    $employeeTempPassword.Length -ge 10 -and
    $employeeTempPassword -cmatch '[A-Z]' -and
    $employeeTempPassword -cmatch '[a-z]' -and
    $employeeTempPassword -match '[0-9]' -and
    $employeeTempPassword -match '[^A-Za-z0-9]'
  ) 'Seguridad de clave temporal de empleado' 'La clave temporal no cumple la politica.'

  $employeeTempLogin = Invoke-Api -Method POST -Endpoint 'login.php' -Body @{
    usuario_login = $employeeEmail
    password_login = $employeeTempPassword
    cliente = 'WEB'
  }
  Assert-Status $employeeTempLogin @(200) 'Acceso temporal del empleado'
  Assert-True ([bool]$employeeTempLogin.Data.usuario.requiere_cambio_password) 'Bandera de cambio obligatorio para empleado' 'La cuenta nueva no exige cambio.'
  $employeeTempToken = [string]$employeeTempLogin.Data.token

  $blockedEmployee = Invoke-Api -Method GET -Endpoint 'listar_ninos.php' -Token $employeeTempToken -Query "id_guarderia=$daycareId"
  Assert-Status $blockedEmployee @(403) 'Bloqueo previo al cambio de clave del empleado'
  Assert-True ($blockedEmployee.Data.codigo -eq 'CAMBIO_PASSWORD_REQUERIDO') 'Codigo de bloqueo obligatorio' 'No se recibio el codigo esperado.'

  $employeePassword = "EmpleadoE2e$runId!"
  $employeePasswordChange = Invoke-Api -Method POST -Endpoint 'cambiar_password.php' -Token $employeeTempToken -Body @{
    password_actual = $employeeTempPassword
    password_nueva = $employeePassword
    confirmacion_password = $employeePassword
  }
  Assert-Status $employeePasswordChange @(200) 'Cambio de clave del empleado'
  $oldEmployeeSession = Invoke-Api -Method GET -Endpoint 'listar_ninos.php' -Token $employeeTempToken -Query "id_guarderia=$daycareId"
  Assert-Status $oldEmployeeSession @(401) 'Revocacion de sesion anterior del empleado'

  $employeeLogin = Invoke-Api -Method POST -Endpoint 'login.php' -Body @{
    usuario_login = $employeeEmail
    password_login = $employeePassword
    cliente = 'WEB'
  }
  Assert-Status $employeeLogin @(200) 'Nuevo acceso del empleado'
  $employeeToken = [string]$employeeLogin.Data.token
  $employeeUserId = [int]$employeeLogin.Data.usuario.id_usuario

  $employeeDeniedList = Invoke-Api -Method GET -Endpoint 'listar_empleados.php' -Token $employeeToken -Query "id_guarderia=$daycareId&id_empleado=$employeeId&estado=TODOS"
  Assert-Status $employeeDeniedList @(403) 'Empleado no administra personal'

  $employeeDeniedChildCreate = Invoke-Api -Method POST -Endpoint 'registrar_nino.php' -Token $employeeToken -Body @{
    id_guarderia = $daycareId
    id_empleado = $employeeId
    nino = @{ nombres = 'No'; apellidos = 'Autorizado'; fecha_nacimiento = '2022-01-01'; genero = 'MASCULINO'; fecha_ingreso = (Get-Date).ToString('yyyy-MM-dd') }
    expediente_medico = @{}
    tutores = @(@{ nombres = 'Tutor'; apellidos = 'No'; parentesco = 'Padre'; email = "no.$runId@safekids.test"; telefono = '8100000010'; recibe_notificaciones = $true; autorizado_recoger = $true })
    contactos_autorizados = @()
  }
  Assert-Status $employeeDeniedChildCreate @(403) 'Empleado no registra menores'

  $employeeList = Invoke-Api -Method GET -Endpoint 'listar_empleados.php' -Token $adminToken -Query "id_guarderia=$daycareId&id_empleado=$adminEmployeeId&estado=TODOS&q=Elena"
  Assert-Status $employeeList @(200) 'Listado administrativo de empleados'
  Assert-True (@($employeeList.Data.empleados).Count -eq 1) 'Busqueda de empleado registrado' 'El listado no encontro exactamente un empleado.'

  $employeeUpdate = Invoke-Api -Method PUT -Endpoint 'actualizar_empleado.php' -Token $adminToken -Body @{
    id_objetivo = $employeeId
    id_guarderia = $daycareId
    id_empleado = $adminEmployeeId
    empleado = @{
      nombres = 'Elena'
      apellidos = 'Pruebas Actualizada'
      email = $employeeEmail
      telefono = '8100000022'
      puesto = 'Educadora'
      fecha_ingreso = (Get-Date).ToString('yyyy-MM-dd')
      rol = 'EMPLEADO'
      notas = 'Perfil actualizado por E2E'
    }
  }
  Assert-Status $employeeUpdate @(200) 'Actualizacion de empleado'

  $tutor1Email = "e2e.tutor1.$runId@safekids.test"
  $tutor2Email = "e2e.tutor2.$runId@safekids.test"
  $today = (Get-Date).ToString('yyyy-MM-dd')
  $childCreate = Invoke-Api -Method POST -Endpoint 'registrar_nino.php' -Token $adminToken -Body @{
    id_guarderia = $daycareId
    id_empleado = $adminEmployeeId
    nino = @{
      nombres = 'Sofia'
      apellidos = 'Prueba E2E'
      fecha_nacimiento = '2022-05-15'
      genero = 'FEMENINO'
      fecha_ingreso = $today
    }
    expediente_medico = @{
      tipo_sangre = 'O+'
      alergias = 'Alergia de prueba'
      padecimientos = ''
      medicamentos_habituales = ''
      restricciones_alimentarias = 'Sin nueces'
      medico_nombre = 'Dra. Prueba'
      medico_telefono = '8100000030'
      institucion_medica = 'Clinica E2E'
      numero_seguro = 'E2E-001'
      indicaciones_emergencia = 'Llamar al tutor principal'
      observaciones = 'Expediente temporal'
    }
    tutores = @(
      @{
        nombres = 'Carlos'; apellidos = 'Tutor E2E'; parentesco = 'Padre'; email = $tutor1Email
        telefono = '8100000031'; telefono_alterno = ''; direccion = 'Domicilio E2E 1'
        recibe_notificaciones = $true; autorizado_recoger = $true
      },
      @{
        nombres = 'Andrea'; apellidos = 'Tutor E2E'; parentesco = 'Madre'; email = $tutor2Email
        telefono = '8100000032'; telefono_alterno = ''; direccion = 'Domicilio E2E 1'
        recibe_notificaciones = $true; autorizado_recoger = $true
      }
    )
    contactos_autorizados = @(
      @{ nombres = 'Ana'; apellidos = 'Contacto Uno'; parentesco = 'Abuela'; telefono = '8100000041'; email = ''; direccion = ''; identificacion_referencia = 'INE E2E 1'; autorizado_recoger = $true; observaciones = '' },
      @{ nombres = 'Luis'; apellidos = 'Contacto Dos'; parentesco = 'Tio'; telefono = '8100000042'; email = ''; direccion = ''; identificacion_referencia = 'INE E2E 2'; autorizado_recoger = $false; observaciones = '' }
    )
  }
  Assert-Status $childCreate @(201) 'Alta de menor con dos tutores y dos contactos'
  $childId = [int]$childCreate.Data.id_nino
  Assert-True (@($childCreate.Data.credenciales_nuevas).Count -eq 2) 'Credenciales para ambos tutores' 'No se generaron dos credenciales.'
  Assert-True (@($childCreate.Data.tutores_vinculados).Count -eq 2) 'Resumen de tutores vinculados' 'La respuesta no incluyo los tutores vinculados.'

  $tutor1Credential = @($childCreate.Data.credenciales_nuevas) | Where-Object { $_.email -eq $tutor1Email }
  $tutor1TempPassword = [string]$tutor1Credential.password_temporal
  Assert-True (
    $tutor1TempPassword.Length -ge 10 -and
    $tutor1TempPassword -cmatch '[A-Z]' -and
    $tutor1TempPassword -cmatch '[a-z]' -and
    $tutor1TempPassword -match '[0-9]' -and
    $tutor1TempPassword -match '[^A-Za-z0-9]'
  ) 'Seguridad de clave temporal de tutor' 'La clave temporal no cumple la politica.'

  $childList = Invoke-Api -Method GET -Endpoint 'listar_ninos.php' -Token $employeeToken -Query "id_guarderia=$daycareId&q=Sofia"
  Assert-Status $childList @(200) 'Empleado consulta listado de menores'
  Assert-True (@($childList.Data.ninos).Count -eq 1) 'Menor visible para el empleado' 'No se encontro el menor creado.'

  $childDetail = Invoke-Api -Method GET -Endpoint 'nino_detalle.php' -Token $adminToken -Query "id_guarderia=$daycareId&id_nino=$childId"
  Assert-Status $childDetail @(200) 'Consulta del expediente infantil'
  Assert-True (@($childDetail.Data.nino.tutores).Count -eq 2) 'Dos tutores vinculados' 'El expediente no contiene dos tutores.'
  Assert-True (@($childDetail.Data.nino.contactos_autorizados).Count -eq 2) 'Dos contactos vinculados' 'El expediente no contiene dos contactos.'

  $tutor1Linked = @($childCreate.Data.tutores_vinculados) | Where-Object { $_.email -eq $tutor1Email }
  $tutorSearchQuery = [uri]::EscapeDataString($tutor1Email)
  $tutorSearch = Invoke-Api -Method GET -Endpoint 'buscar_tutores.php' -Token $adminToken -Query "id_guarderia=$daycareId&id_empleado=$adminEmployeeId&q=$tutorSearchQuery"
  Assert-Status $tutorSearch @(200) 'Busqueda de tutor existente'
  Assert-True (@($tutorSearch.Data.tutores).Count -eq 1) 'Tutor existente disponible para vincular' 'La busqueda no encontro la cuenta creada.'

  $tutorsForUpdate = @($childDetail.Data.nino.tutores | ForEach-Object {
    @{
      id_tutor = [int]$_.id_tutor; nombres = [string]$_.nombres; apellidos = [string]$_.apellidos
      parentesco = [string]$_.parentesco; email = [string]$_.email; telefono = [string]$_.telefono
      telefono_alterno = [string]$_.telefono_alterno; direccion = [string]$_.direccion
      recibe_notificaciones = [bool]$_.recibe_notificaciones; autorizado_recoger = [bool]$_.autorizado_recoger
    }
  })
  $contactsForUpdate = @($childDetail.Data.nino.contactos_autorizados | ForEach-Object {
    @{
      id_contacto = [int]$_.id_contacto; nombres = [string]$_.nombres; apellidos = [string]$_.apellidos
      parentesco = [string]$_.parentesco; telefono = [string]$_.telefono; email = [string]$_.email
      direccion = [string]$_.direccion; identificacion_referencia = [string]$_.identificacion_referencia
      autorizado_recoger = [bool]$_.autorizado_recoger; observaciones = [string]$_.observaciones
    }
  })
  $childUpdate = Invoke-Api -Method PUT -Endpoint 'actualizar_nino.php' -Token $adminToken -Body @{
    id_nino = $childId
    id_guarderia = $daycareId
    id_empleado = $adminEmployeeId
    nino = @{ nombres = 'Sofia Actualizada'; apellidos = 'Prueba E2E'; fecha_nacimiento = '2022-05-15'; genero = 'FEMENINO'; fecha_ingreso = $today }
    expediente_medico = @{ tipo_sangre = 'O+'; alergias = 'Alergia actualizada'; padecimientos = ''; medicamentos_habituales = ''; restricciones_alimentarias = 'Sin nueces'; medico_nombre = 'Dra. Prueba'; medico_telefono = '8100000030'; institucion_medica = 'Clinica E2E'; numero_seguro = 'E2E-001'; indicaciones_emergencia = 'Llamar al tutor principal'; observaciones = 'Actualizado' }
    tutores = $tutorsForUpdate
    contactos_autorizados = $contactsForUpdate
  }
  Assert-Status $childUpdate @(200) 'Actualizacion del expediente infantil'

  $child2Create = Invoke-Api -Method POST -Endpoint 'registrar_nino.php' -Token $adminToken -Body @{
    id_guarderia = $daycareId
    id_empleado = $adminEmployeeId
    nino = @{ nombres = 'Mateo'; apellidos = 'Prueba E2E'; fecha_nacimiento = '2023-04-10'; genero = 'MASCULINO'; fecha_ingreso = $today }
    expediente_medico = @{}
    tutores = @(@{ id_tutor = [int]$tutor1Linked.id_tutor; parentesco = 'Padre'; recibe_notificaciones = $true; autorizado_recoger = $true })
    contactos_autorizados = @()
  }
  Assert-Status $child2Create @(201) 'Segundo hijo vinculado a un tutor existente'
  Assert-True (@($child2Create.Data.credenciales_nuevas).Count -eq 0) 'Reutilizacion de cuenta del tutor' 'Se genero una cuenta duplicada para el tutor.'

  $tutorWrongClient = Invoke-Api -Method POST -Endpoint 'login.php' -Body @{
    usuario_login = $tutor1Email
    password_login = $tutor1TempPassword
    cliente = 'WEB'
  }
  Assert-Status $tutorWrongClient @(403) 'Tutor rechazado en el panel web'

  $tutorTempLogin = Invoke-Api -Method POST -Endpoint 'login.php' -Body @{
    usuario_login = $tutor1Email
    password_login = $tutor1TempPassword
    cliente = 'ANDROID'
  }
  Assert-Status $tutorTempLogin @(200) 'Acceso temporal del tutor en Android'
  $tutorTempToken = [string]$tutorTempLogin.Data.token
  $tutorUserId = [int]$tutorTempLogin.Data.usuario.id_usuario
  $tutorBlocked = Invoke-Api -Method GET -Endpoint 'mis_hijos.php' -Token $tutorTempToken -Query "id_guarderia=$daycareId&id_usuario=$tutorUserId"
  Assert-Status $tutorBlocked @(403) 'Bloqueo previo al cambio de clave del tutor'

  $tutorPassword = "TutorE2e$runId!"
  $tutorPasswordChange = Invoke-Api -Method POST -Endpoint 'cambiar_password.php' -Token $tutorTempToken -Body @{
    password_actual = $tutor1TempPassword
    password_nueva = $tutorPassword
    confirmacion_password = $tutorPassword
  }
  Assert-Status $tutorPasswordChange @(200) 'Cambio de clave del tutor'

  $tutorLogin = Invoke-Api -Method POST -Endpoint 'login.php' -Body @{
    usuario_login = $tutor1Email
    password_login = $tutorPassword
    cliente = 'ANDROID'
  }
  Assert-Status $tutorLogin @(200) 'Nuevo acceso del tutor'
  $tutorToken = [string]$tutorLogin.Data.token

  $childrenOfTutor = Invoke-Api -Method GET -Endpoint 'mis_hijos.php' -Token $tutorToken -Query "id_guarderia=$daycareId&id_usuario=$tutorUserId"
  Assert-Status $childrenOfTutor @(200) 'Consulta movil de hijos'
  Assert-True (@($childrenOfTutor.Data.hijos).Count -eq 2) 'Cuenta con dos hijos' 'El tutor no puede consultar ambos hijos.'

  $profileOfChild = Invoke-Api -Method GET -Endpoint 'perfil_hijo.php' -Token $tutorToken -Query "id_guarderia=$daycareId&id_usuario=$tutorUserId&id_nino=$childId"
  Assert-Status $profileOfChild @(200) 'Perfil movil del hijo'

  $crossTenantTutor = Invoke-Api -Method GET -Endpoint 'mis_hijos.php' -Token $tutorToken -Query "id_guarderia=1&id_usuario=$tutorUserId"
  Assert-Status $crossTenantTutor @(403) 'Aislamiento de guarderia para tutor'

  $eventTypeId = [int](Get-MySqlScalar "SELECT id_tipo_evento FROM tipos_evento WHERE codigo='ALIMENTACION' LIMIT 1;")
  $reportCreate = Invoke-Api -Method POST -Endpoint 'crear_reporte.php' -Token $employeeToken -Body @{
    id_guarderia = $daycareId
    id_empleado = $employeeId
    id_nino = $childId
    id_tipo_evento = $eventTypeId
    fecha_hora_evento = (Get-Date).ToString('yyyy-MM-ddTHH:mm')
    titulo = 'Alimentacion E2E'
    descripcion = 'Comio correctamente durante la prueba.'
    detalle_json = @{ alimento = 'Fruta'; cantidad_consumida = 'Completa' }
    nivel = 'INFORMATIVO'
  }
  Assert-Status $reportCreate @(201) 'Empleado registra reporte diario'
  $reportId = [int]$reportCreate.Data.id_evento

  $staffReports = Invoke-Api -Method GET -Endpoint 'listar_reportes.php' -Token $employeeToken -Query "id_guarderia=$daycareId&id_empleado=$employeeId&id_nino=$childId&desde=$today&hasta=$today&tipo=TODOS"
  Assert-Status $staffReports @(200) 'Historial web de reportes'
  Assert-True (@($staffReports.Data.reportes | Where-Object { [int]$_.id_evento -eq $reportId }).Count -eq 1) 'Reporte visible en panel web' 'No se encontro el reporte creado.'

  $tutorReports = Invoke-Api -Method GET -Endpoint 'reportes_de_mi_hijo.php' -Token $tutorToken -Query "id_guarderia=$daycareId&id_usuario=$tutorUserId&id_nino=$childId&desde=$today&hasta=$today&tipo=TODOS"
  Assert-Status $tutorReports @(200) 'Historial movil de reportes'
  Assert-True (@($tutorReports.Data.reportes | Where-Object { [int]$_.id_evento -eq $reportId }).Count -eq 1) 'Reporte visible para el tutor' 'El reporte no llego a la app movil.'

  $personalNotification = Invoke-Api -Method POST -Endpoint 'crear_notificacion.php' -Token $employeeToken -Body @{
    id_guarderia = $daycareId
    id_empleado = $employeeId
    id_nino = $childId
    alcance = 'PERSONAL'
    tipo_codigo = 'AVISO_PERSONAL'
    titulo = 'Aviso personal E2E'
    mensaje = 'Mensaje dirigido a los tutores del menor.'
    prioridad = 'IMPORTANTE'
    requiere_confirmacion = $true
  }
  Assert-Status $personalNotification @(201) 'Notificacion personal'
  Assert-True ([int]$personalNotification.Data.destinatarios -eq 2) 'Destinatarios personales correctos' 'La notificacion no se envio a ambos tutores.'
  $personalNotificationId = [int]$personalNotification.Data.id_notificacion

  $staffPersonalHistory = Invoke-Api -Method GET -Endpoint 'listar_notificaciones_nino.php' -Token $employeeToken -Query "id_guarderia=$daycareId&id_empleado=$employeeId&id_nino=$childId&buscar=Aviso&tipo=AVISO_PERSONAL&prioridad=IMPORTANTE&desde=$today&hasta=$today"
  Assert-Status $staffPersonalHistory @(200) 'Historial web de notificaciones personales'
  $staffPersonalRecord = @(
    $staffPersonalHistory.Data.notificaciones |
      Where-Object { [int]$_.id_notificacion -eq $personalNotificationId }
  )
  Assert-True ($staffPersonalRecord.Count -eq 1) 'Notificacion personal visible en panel web' 'El historial no devolvio la notificacion creada.'
  Assert-True (
    [int]$staffPersonalRecord[0].entrega.destinatarios -eq 2 -and
    @($staffPersonalRecord[0].destinatarios).Count -eq 2
  ) 'Detalle de tutores destinatarios' 'El historial no devolvio ambos tutores.'

  $tutorDeniedPersonalHistory = Invoke-Api -Method GET -Endpoint 'listar_notificaciones_nino.php' -Token $tutorToken -Query "id_guarderia=$daycareId&id_empleado=$employeeId&id_nino=$childId&tipo=TODOS&prioridad=TODAS&desde=$today&hasta=$today"
  Assert-Status $tutorDeniedPersonalHistory @(403) 'Tutor rechazado en historial web personal'

  $crossTenantPersonalHistory = Invoke-Api -Method GET -Endpoint 'listar_notificaciones_nino.php' -Token $employeeToken -Query "id_guarderia=1&id_empleado=$employeeId&id_nino=$childId&tipo=TODOS&prioridad=TODAS&desde=$today&hasta=$today"
  Assert-Status $crossTenantPersonalHistory @(403) 'Aislamiento del historial personal por guarderia'

  $globalNotification = Invoke-Api -Method POST -Endpoint 'crear_notificacion.php' -Token $employeeToken -Body @{
    id_guarderia = $daycareId
    id_empleado = $employeeId
    id_nino = $null
    alcance = 'GLOBAL'
    tipo_codigo = 'AVISO_GENERAL'
    titulo = 'Aviso global E2E'
    mensaje = 'Mensaje para todos los tutores de la guarderia.'
    prioridad = 'NORMAL'
    requiere_confirmacion = $false
  }
  Assert-Status $globalNotification @(201) 'Notificacion global'
  Assert-True ([int]$globalNotification.Data.destinatarios -eq 2) 'Destinatarios globales correctos' 'La notificacion global no alcanzo a todos los tutores.'
  $globalNotificationId = [int]$globalNotification.Data.id_notificacion

  $imageNotificationId = 0
  if ($IncludeCloudinary) {
    $testImagePath = Join-Path ([System.IO.Path]::GetTempPath()) "safekids-e2e-$runId.png"
    $imageBytes = [Convert]::FromBase64String(
      'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9ZQmcAAAAASUVORK5CYII='
    )
    [System.IO.File]::WriteAllBytes($testImagePath, $imageBytes)

    $imageNotification = Invoke-ApiMultipart `
      -Endpoint 'crear_notificacion.php' `
      -Token $employeeToken `
      -Fields @{
        id_guarderia = $daycareId
        id_empleado = $employeeId
        alcance = 'GLOBAL'
        tipo_codigo = 'AVISO_GENERAL'
        titulo = 'Aviso con imagen E2E'
        mensaje = 'Notificacion temporal con evidencia visual.'
        prioridad = 'NORMAL'
        requiere_confirmacion = '0'
      } `
      -FilePath $testImagePath
    Assert-Status $imageNotification @(201) 'Notificacion global con imagen'
    Assert-True (
      [string]$imageNotification.Data.imagen_url -like 'https://res.cloudinary.com/*'
    ) 'URL segura de imagen' 'Cloudinary no devolvio una URL HTTPS.'
    $imageNotificationId = [int]$imageNotification.Data.id_notificacion
    $uploadedNotificationPublicId = Get-MySqlScalar "
      SELECT COALESCE(imagen_public_id,'')
      FROM notificaciones
      WHERE id_notificacion=$imageNotificationId;
    "
    Assert-True (
      $uploadedNotificationPublicId -ne ''
    ) 'Public ID de imagen persistido' 'No se guardo el identificador de Cloudinary.'
  }

  $staffNotices = Invoke-Api -Method GET -Endpoint 'listar_avisos.php' -Token $employeeToken -Query "id_guarderia=$daycareId&id_empleado=$employeeId&tipo=TODOS&prioridad=TODAS&desde=$today&hasta=$today"
  Assert-Status $staffNotices @(200) 'Historial web de avisos globales'
  Assert-True (@($staffNotices.Data.avisos | Where-Object { [int]$_.id_notificacion -eq $globalNotificationId }).Count -eq 1) 'Aviso global visible para empleados' 'El aviso no aparece en el historial.'
  if ($IncludeCloudinary) {
    Assert-True (
      @(
        $staffNotices.Data.avisos |
          Where-Object {
            [int]$_.id_notificacion -eq $imageNotificationId -and
            -not [string]::IsNullOrWhiteSpace([string]$_.imagen_url)
          }
      ).Count -eq 1
    ) 'Imagen visible en historial web' 'El historial no devolvio el adjunto.'
  }

  $tutorNotifications = Invoke-Api -Method GET -Endpoint 'notificaciones_de_mi_hijo.php' -Token $tutorToken -Query "id_guarderia=$daycareId&id_usuario=$tutorUserId&id_nino=$childId&desde=$today&hasta=$today&tipo=TODOS"
  Assert-Status $tutorNotifications @(200) 'Historial movil de notificaciones'
  Assert-True (@($tutorNotifications.Data.notificaciones | Where-Object { [int]$_.id_notificacion -eq $personalNotificationId }).Count -eq 1) 'Notificacion personal visible en Android' 'No aparece la notificacion personal.'
  Assert-True (@($tutorNotifications.Data.notificaciones | Where-Object { [int]$_.id_notificacion -eq $globalNotificationId }).Count -eq 1) 'Notificacion global visible en Android' 'No aparece la notificacion global.'
  if ($IncludeCloudinary) {
    Assert-True (
      @(
        $tutorNotifications.Data.notificaciones |
          Where-Object {
            [int]$_.id_notificacion -eq $imageNotificationId -and
            -not [string]::IsNullOrWhiteSpace([string]$_.imagen_url)
          }
      ).Count -eq 1
    ) 'Imagen visible en historial Android' 'La API movil no devolvio el adjunto.'
  }

  $notificationConfirm = Invoke-Api -Method POST -Endpoint 'marcar_notificacion_vista.php' -Token $tutorToken -Body @{
    id_guarderia = $daycareId
    id_usuario = $tutorUserId
    id_nino = $childId
    id_notificacion = $personalNotificationId
    confirmar = $true
    respuesta = 'Confirmada en prueba E2E'
  }
  Assert-Status $notificationConfirm @(200) 'Lectura y confirmacion de notificacion'
  Assert-True ([bool]$notificationConfirm.Data.notificacion.confirmada) 'Estado confirmado persistido' 'La confirmacion no fue guardada.'

  $confirmedStaffHistory = Invoke-Api -Method GET -Endpoint 'listar_notificaciones_nino.php' -Token $employeeToken -Query "id_guarderia=$daycareId&id_empleado=$employeeId&id_nino=$childId&tipo=TODOS&prioridad=TODAS&desde=$today&hasta=$today"
  Assert-Status $confirmedStaffHistory @(200) 'Actualizacion del seguimiento personal'
  $confirmedStaffRecord = @(
    $confirmedStaffHistory.Data.notificaciones |
      Where-Object { [int]$_.id_notificacion -eq $personalNotificationId }
  )[0]
  Assert-True (
    [int]$confirmedStaffRecord.entrega.vistos -eq 1 -and
    [int]$confirmedStaffRecord.entrega.confirmados -eq 1 -and
    @(
      $confirmedStaffRecord.destinatarios |
        Where-Object {
          [int]$_.id_usuario -eq $tutorUserId -and
          [bool]$_.confirmada -and
          [string]$_.respuesta -eq 'Confirmada en prueba E2E'
        }
    ).Count -eq 1
  ) 'Lectura y respuesta visibles para empleados' 'El seguimiento web no reflejo la confirmacion del tutor.'

  $fakeFcmToken = "e2e_fcm_token_${runId}_abcdefghijklmnopqrstuvwxyz"
  $deviceRegister = Invoke-Api -Method POST -Endpoint 'registrar_dispositivo.php' -Token $tutorToken -Body @{
    id_guarderia = $daycareId
    id_usuario = $tutorUserId
    token_fcm = $fakeFcmToken
    nombre_dispositivo = 'Emulador E2E'
  }
  Assert-Status $deviceRegister @(200) 'Registro de dispositivo movil'
  $deviceUnregister = Invoke-Api -Method POST -Endpoint 'desregistrar_dispositivo.php' -Token $tutorToken -Body @{
    id_guarderia = $daycareId
    id_usuario = $tutorUserId
    token_fcm = $fakeFcmToken
  }
  Assert-Status $deviceUnregister @(200) 'Baja de dispositivo movil'

  $childDeactivate = Invoke-Api -Method PATCH -Endpoint 'cambiar_estado_nino.php' -Token $adminToken -Body @{
    id_nino = $childId; id_guarderia = $daycareId; id_empleado = $adminEmployeeId; estado = 'INACTIVO'
  }
  Assert-Status $childDeactivate @(200) 'Baja del expediente infantil'
  $reportOnInactiveChild = Invoke-Api -Method POST -Endpoint 'crear_reporte.php' -Token $employeeToken -Body @{
    id_guarderia = $daycareId; id_empleado = $employeeId; id_nino = $childId; id_tipo_evento = $eventTypeId
    fecha_hora_evento = (Get-Date).ToString('yyyy-MM-ddTHH:mm'); titulo = 'No permitido'; descripcion = 'No debe guardarse'; detalle_json = @{}; nivel = 'INFORMATIVO'
  }
  Assert-Status $reportOnInactiveChild @(422) 'Expediente inactivo rechaza reportes'
  $childReactivate = Invoke-Api -Method PATCH -Endpoint 'cambiar_estado_nino.php' -Token $adminToken -Body @{
    id_nino = $childId; id_guarderia = $daycareId; id_empleado = $adminEmployeeId; estado = 'ACTIVO'
  }
  Assert-Status $childReactivate @(200) 'Reactivacion del expediente infantil'

  $employeeDeactivate = Invoke-Api -Method PATCH -Endpoint 'cambiar_estado_empleado.php' -Token $adminToken -Body @{
    id_objetivo = $employeeId; id_guarderia = $daycareId; id_empleado = $adminEmployeeId; estado = 'INACTIVO'
  }
  Assert-Status $employeeDeactivate @(200) 'Baja de empleado'
  $inactiveEmployeeSession = Invoke-Api -Method GET -Endpoint 'listar_ninos.php' -Token $employeeToken -Query "id_guarderia=$daycareId"
  Assert-Status $inactiveEmployeeSession @(401) 'Sesion bloqueada al dar de baja al empleado'
  $employeeReactivate = Invoke-Api -Method PATCH -Endpoint 'cambiar_estado_empleado.php' -Token $adminToken -Body @{
    id_objetivo = $employeeId; id_guarderia = $daycareId; id_empleado = $adminEmployeeId; estado = 'ACTIVO'
  }
  Assert-Status $employeeReactivate @(200) 'Reactivacion de empleado'
  $revokedEmployeeSession = Invoke-Api -Method GET -Endpoint 'listar_ninos.php' -Token $employeeToken -Query "id_guarderia=$daycareId"
  Assert-Status $revokedEmployeeSession @(401) 'Sesion antigua no revive al reactivar empleado'

  $selfDeactivate = Invoke-Api -Method PATCH -Endpoint 'cambiar_estado_empleado.php' -Token $adminToken -Body @{
    id_objetivo = $adminEmployeeId; id_guarderia = $daycareId; id_empleado = $adminEmployeeId; estado = 'INACTIVO'
  }
  Assert-Status $selfDeactivate @(422) 'Administrador no puede darse de baja a si mismo'

  $tutorLogout = Invoke-Api -Method POST -Endpoint 'logout.php' -Token $tutorToken -Body @{}
  Assert-Status $tutorLogout @(200) 'Cierre de sesion movil'
  $loggedOutTutor = Invoke-Api -Method GET -Endpoint 'mis_hijos.php' -Token $tutorToken -Query "id_guarderia=$daycareId&id_usuario=$tutorUserId"
  Assert-Status $loggedOutTutor @(401) 'Token movil revocado al salir'

  $adminLogout = Invoke-Api -Method POST -Endpoint 'logout.php' -Token $adminToken -Body @{}
  Assert-Status $adminLogout @(200) 'Cierre de sesion administrativo'
  $loggedOutAdmin = Invoke-Api -Method GET -Endpoint 'listar_ninos.php' -Token $adminToken -Query "id_guarderia=$daycareId"
  Assert-Status $loggedOutAdmin @(401) 'Token web revocado al salir'
} catch {
  $failure = $_
} finally {
  try {
    if ($uploadedNotificationPublicId -ne '') {
      $env:SAFEKIDS_TEST_PUBLIC_ID = $uploadedNotificationPublicId
      try {
        & $PhpExecutable -r "require 'C:/xampp/htdocs/SafeKids-api/cloudinary.php'; cloudinary_eliminar_imagen(getenv('SAFEKIDS_TEST_PUBLIC_ID'));"
        if ($LASTEXITCODE -ne 0) {
          throw 'No fue posible eliminar la imagen temporal de Cloudinary.'
        }
      } finally {
        Remove-Item Env:SAFEKIDS_TEST_PUBLIC_ID -ErrorAction SilentlyContinue
      }
    }
    if ($testImagePath -ne '' -and (Test-Path -LiteralPath $testImagePath)) {
      Remove-Item -LiteralPath $testImagePath -Force
    }
    if ($daycareId -le 0 -and $daycareEmail -ne '') {
      $recoveredId = Get-MySqlScalar "SELECT COALESCE(MAX(id_guarderia),0) FROM guarderias WHERE email='$daycareEmail';"
      $daycareId = [int]$recoveredId
    }
    Remove-TestDaycare $daycareId
  } catch {
    if ($null -eq $failure) {
      $failure = $_
    } else {
      Write-Warning "La prueba fallo y la limpieza tambien: $($_.Exception.Message)"
    }
  }
}

$checks | Format-Table -AutoSize
Write-Host "`nPruebas completadas: $($checks.Count)"
Write-Host "Datos temporales eliminados: $($daycareId -gt 0)"

if ($null -ne $failure) {
  throw $failure
}
