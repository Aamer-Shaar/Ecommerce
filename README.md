# Ecommerce API (Laravel)

مشروع Ecommerce بسيط مبني بـ Laravel، يوفر APIs للمنتجات والتصنيفات والسلة والطلبات مع توثيق JWT.

## المتطلبات

- PHP 8.1+
- Composer
- قاعدة بيانات (MySQL / MariaDB)

## التشغيل

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan jwt:secret
php artisan migrate
php artisan serve
```

## تنسيق الاستجابات

معظم الاستجابات تأتي بهذا الشكل:

```json
{
  "success": true,
  "message": "string",
  "data": {}
}
```

وفي حال الخطأ:

```json
{
  "success": false,
  "message": "string",
  "errors": null
}
```

## المصادقة (JWT)

- بعد تسجيل الدخول/التسجيل ستحصل على `access_token`.
- أرسل التوكن في الهيدر:

```http
Authorization: Bearer <access_token>
```

## الـ API Endpoints

Base URL (افتراضي عند استخدام `php artisan serve`):

`http://localhost:8000/api`

### Auth

#### POST /register

Body:

```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "secret123",
  "password_confirmation": "secret123"
}
```

#### POST /login

Body:

```json
{
  "email": "john@example.com",
  "password": "secret123"
}
```

#### POST /logout (Protected)

Body: لا يوجد

#### GET /me (Protected)

Body: لا يوجد

#### POST /refresh (Protected)

Body: لا يوجد

### Products

#### GET /products

Query (اختياري):

- `category_id` (رقم التصنيف)
- `page` (رقم الصفحة)

Body: لا يوجد

#### GET /products/{id}

Body: لا يوجد

#### POST /products (Admin + Protected)

Body:

```json
{
  "name": "iPhone 15",
  "description": "Product description",
  "price": 999.99,
  "category_id": 1
}
```

#### PUT /products/{id} (Admin + Protected)

Body (جزئي/اختياري):

```json
{
  "name": "New name",
  "description": "New description",
  "price": 120.5,
  "category_id": 2
}
```

#### DELETE /products/{id} (Admin + Protected)

Body: لا يوجد

### Categories

#### GET /categories

Body: لا يوجد

#### GET /categories/{id}

Body: لا يوجد

#### POST /categories (Admin + Protected)

Body:

```json
{
  "name": "Phones",
  "description": "Optional description"
}
```

#### PUT /categories/{id} (Admin + Protected)

Body (جزئي/اختياري):

```json
{
  "name": "New category name",
  "description": "Optional description"
}
```

#### DELETE /categories/{id} (Admin + Protected)

Body: لا يوجد

### Cart (Protected)

#### GET /cart

Body: لا يوجد

#### POST /cart

Body:

```json
{
  "product_id": 1,
  "quantity": 2
}
```

#### PUT /cart/{id}

Body:

```json
{
  "quantity": 3
}
```

#### DELETE /cart/{id}

Body: لا يوجد

### Orders (Protected)

#### GET /orders

Body: لا يوجد

#### GET /orders/{id}

Body: لا يوجد

#### POST /checkout

ينشئ طلباً من عناصر السلة الحالية ويخصم الكميات من المخزون.

Body: لا يوجد

### Inventory

#### GET /inventory/{productId} (Protected)

Body: لا يوجد

#### PUT /inventory/{productId} (Admin + Protected)

Body:

```json
{
  "quantity": 100
}
```

### Health Check

#### GET /test

Body: لا يوجد
