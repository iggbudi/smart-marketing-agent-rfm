<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Budget Summary BatikRFM - Rp 80.000.000</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f0f0f0;
        }
        
        .container {
            background-color: white;
            padding: 20px;
            border-radius: 5px;
            max-width: 1000px;
            margin: 0 auto;
        }
        
        .header {
            background-color: #333;
            color: white;
            padding: 20px;
            text-align: center;
            margin-bottom: 20px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        
        th {
            background-color: #f2f2f2;
        }
        
        .price {
            text-align: right;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>BUDGET SUMMARY BatikRFM</h1>
            <p>Marketing Intelligence untuk Pengrajin Batik</p>
            <div style="font-size: 24px; color: #4CAF50;">Rp 80.000.000</div>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>No</th>
                    <th>Item</th>
                    <th>Provider/Keterangan</th>
                    <th>Duration</th>
                    <th>Biaya (Rp)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>A</td>
                    <td><strong>INFRASTRUCTURE</strong></td>
                    <td colspan="2"></td>
                    <td class="price">48.000.000</td>
                </tr>
                <tr>
                    <td>1</td>
                    <td>VPS Production</td>
                    <td>DigitalOcean</td>
                    <td>12 bulan</td>
                    <td class="price">7.200.000</td>
                </tr>
                <tr>
                    <td>2</td>
                    <td>Vercel Pro</td>
                    <td>Vercel</td>
                    <td>12 bulan</td>
                    <td class="price">3.600.000</td>
                </tr>
                <tr>
                    <td>B</td>
                    <td><strong>TIM TEKNIS</strong></td>
                    <td colspan="2"></td>
                    <td class="price">32.000.000</td>
                </tr>
                <tr>
                    <td>3</td>
                    <td>Lead Full-Stack Developer</td>
                    <td>Senior Level</td>
                    <td>3 bulan</td>
                    <td class="price">24.000.000</td>
                </tr>
                <tr style="background-color: #333; color: white; font-weight: bold;">
                    <td colspan="4">GRAND TOTAL</td>
                    <td class="price">80.000.000</td>
                </tr>
            </tbody>
        </table>
    </div>
</body>
</html>
