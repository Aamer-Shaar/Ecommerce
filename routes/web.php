<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Request; // تم إضافة استيراد الـ Request هنا

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    // جلب رقم المنفذ الحقيقي الذي يعالج الطلب الحالي من السيرفر
    $currentPort = Request::server('SERVER_PORT'); 

    return "
    <div style='text-align: center; margin-top: 15%; font-family: Arial, sans-serif;'>
        <h1>مشروع محاكاة توزيع الأحمال (Load Balancing)</h1>
        <p style='font-size: 1.5rem;'>
            الطلب الحالي يتم معالجته بواسطة الخادم ذو المنفذ: 
            <strong style='color: #e53e3e;'>{$currentPort}</strong>
        </p>
        <p>[ قم بتحديث الصفحة أو استخدام التصفح الخفي لمشاهدة التغير ]</p>
    </div>
    ";
}); // إغلاق الدالة والتوجيه بشكل صحيح هنا