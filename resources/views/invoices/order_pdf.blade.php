<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="utf-8">

<style>
    body {
        font-family: DejaVu Sans, Arial, sans-serif;
        direction: ltr;
        text-align: left;
        background: #f9f9f9;
        margin: 0;
        padding: 0;
    }

    .invoice-box {
        border: 1px solid #eee;
        padding: 30px;
        max-width: 800px;
        margin: 40px auto;
        background: #fff;
        font-size: 14px;
        line-height: 1.8;
        color: #333;
        box-shadow: 0 0 10px rgba(0,0,0,0.05);
        border-radius: 8px;
    }

    h1 {
        text-align: center;
        margin-bottom: 20px;
        font-size: 22px;
    }

    hr {
        border: none;
        border-top: 1px solid #eee;
        margin: 15px 0;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 10px;
    }

    table td {
        padding: 12px;
        border-bottom: 1px solid #eee;
    }

    table tr.heading td {
        background: #f1f1f1;
        font-weight: bold;
    }

    h3 {
        margin-top: 20px;
        text-align: right;
    }
</style>
</head>

<body>

<div class="invoice-box">

    <h1>INVOICE #{{ $order->order_number }}</h1>

    <p><strong>Order Date:</strong> {{ $order->created_at->format('Y-m-d') }}</p>
    <p><strong>Customer Name:</strong> {{ $order->user->name }}</p>

    <hr>

    <table>
        <tr class="heading">
            <td>Product</td>
            <td>Quantity</td>
            <td>Price</td>
        </tr>

        @foreach($order->items as $item)
        <tr>
            <td>{{ $item->product->name }}</td>
            <td>{{ $item->quantity }}</td>
            <td>{{ $item->price }}</td>
        </tr>
        @endforeach
    </table>

    <h3>Total: {{ $order->total_amount }}</h3>

    <div class="footer">
        Thank you for your purchase!
    </div>

</div>

</body>
</html>