param(
    [string]$BaseUrl = 'http://localhost/api',
    [int]$RegisterIterations = 5,
    [int]$RegularIterations = 5,
    [int]$AdminIterations = 3,
    [int]$ProductId = 1
)

$ErrorActionPreference = 'Stop'

function Invoke-Api {
    param(
        [Parameter(Mandatory = $true)][string]$Method,
        [Parameter(Mandatory = $true)][string]$Endpoint,
        [string]$Token = '',
        [object]$Body = $null
    )

    $headers = @{
        Accept = 'application/json'
    }

    if ($Token) {
        $headers.Authorization = "Bearer $Token"
    }

    $uri = "$BaseUrl$Endpoint"

    if ($null -ne $Body) {
        $jsonBody = $Body | ConvertTo-Json -Depth 10

        return Invoke-RestMethod -Method $Method -Uri $uri -Headers $headers -ContentType 'application/json' -Body $jsonBody
    }

    return Invoke-RestMethod -Method $Method -Uri $uri -Headers $headers
}

function New-BenchmarkUser {
    $email = 'benchmark_' + [guid]::NewGuid().ToString('N').Substring(0, 8) + '@example.com'
    $password = 'secret123'

    Invoke-Api -Method 'POST' -Endpoint '/register' -Body @{
        name = 'Benchmark User'
        email = $email
        password = $password
        password_confirmation = $password
    } | Out-Null

    return @{
        email = $email
        password = $password
    }
}

Write-Host 'Running registration benchmarks...'

$benchmarkCredentials = $null

for ($i = 1; $i -le $RegisterIterations; $i++) {
    $benchmarkCredentials = New-BenchmarkUser
}

Write-Host 'Running authenticated user benchmarks...'

for ($i = 1; $i -le $RegularIterations; $i++) {
    $login = Invoke-Api -Method 'POST' -Endpoint '/login' -Body @{
        email = $benchmarkCredentials.email
        password = $benchmarkCredentials.password
    }

    $token = $login.data.access_token

    $refresh = Invoke-Api -Method 'POST' -Endpoint '/refresh' -Token $token
    $token = $refresh.data.access_token

    Invoke-Api -Method 'GET' -Endpoint '/me' -Token $token | Out-Null

    Invoke-Api -Method 'POST' -Endpoint '/cart' -Token $token -Body @{
        product_id = $ProductId
        quantity = 1
    } | Out-Null

    $cart = Invoke-Api -Method 'GET' -Endpoint '/cart' -Token $token
    $cartItemId = ($cart.data.items | Select-Object -First 1).id

    Invoke-Api -Method 'PUT' -Endpoint "/cart/$cartItemId" -Token $token -Body @{
        quantity = 2
    } | Out-Null

    $checkout = Invoke-Api -Method 'POST' -Endpoint '/checkout' -Token $token
    $orderId = $checkout.data.id

    Invoke-Api -Method 'GET' -Endpoint '/orders' -Token $token | Out-Null
    Invoke-Api -Method 'GET' -Endpoint "/orders/$orderId" -Token $token | Out-Null
    Invoke-Api -Method 'POST' -Endpoint '/logout' -Token $token | Out-Null
}

Write-Host 'Running admin benchmarks...'

$adminLogin = Invoke-Api -Method 'POST' -Endpoint '/login' -Body @{
    email = 'admin@example.com'
    password = 'password'
}

$adminToken = $adminLogin.data.access_token

for ($i = 1; $i -le $AdminIterations; $i++) {
    $categoryName = 'Benchmark Category ' + [guid]::NewGuid().ToString('N').Substring(0, 8)
    $productName = 'Benchmark Product ' + [guid]::NewGuid().ToString('N').Substring(0, 8)

    $category = Invoke-Api -Method 'POST' -Endpoint '/categories' -Token $adminToken -Body @{
        name = $categoryName
        description = 'Temporary category for benchmark'
    }

    $categoryId = $category.data.id

    Invoke-Api -Method 'PUT' -Endpoint "/categories/$categoryId" -Token $adminToken -Body @{
        name = "$categoryName Updated"
        description = 'Updated temporary category for benchmark'
    } | Out-Null

    $product = Invoke-Api -Method 'POST' -Endpoint '/products' -Token $adminToken -Body @{
        name = $productName
        description = 'Temporary product for benchmark'
        price = 99.99
        category_id = $categoryId
    }

    $productId = $product.data.id

    Invoke-Api -Method 'PUT' -Endpoint "/products/$productId" -Token $adminToken -Body @{
        name = "$productName Updated"
        description = 'Updated temporary product for benchmark'
        price = 149.99
        category_id = $categoryId
    } | Out-Null

    Invoke-Api -Method 'PUT' -Endpoint "/inventory/$productId" -Token $adminToken -Body @{
        quantity = 25
    } | Out-Null

    Invoke-Api -Method 'DELETE' -Endpoint "/products/$productId" -Token $adminToken | Out-Null
    Invoke-Api -Method 'DELETE' -Endpoint "/categories/$categoryId" -Token $adminToken | Out-Null
}

Write-Host 'Full API benchmark run completed.'
