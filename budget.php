<?php
require_once 'config/auth.php';

// Require UMKM owner access
requireAuth(['umkm_owner']);

$user = getCurrentUser();
$business = auth()->getUserBusiness($user['id']);
if (!$business) {
    die('Error: No business associated with your account. Please contact administrator.');
}

error_reporting(E_ALL);
ini_set('display_errors', 1);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rencana Anggaran Biaya - Smart Marketing Agent RFM - Rp 80.000.000</title>
    
    <!-- PDF Generation Libraries -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
            padding: 20px;
            color: #333;
            min-height: 100vh;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            min-height: 100vh;
            display: block;
            visibility: visible;
        }
        
        .header {
            background-color: #2c3e50;
            color: white;
            padding: 40px 30px;
            text-align: center;
            display: block;
            visibility: visible;
        }
        
        .header h1 {
            font-size: 32px;
            margin-bottom: 8px;
            font-weight: 700;
        }
        
        .header .subtitle {
            font-size: 16px;
            margin-bottom: 20px;
            opacity: 0.9;
        }
        
        .company-info {
            background-color: rgba(255, 255, 255, 0.1);
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            backdrop-filter: blur(5px);
        }
        
        .company-name {
            font-size: 28px;
            font-weight: bold;
            color: #3498db;
            margin-bottom: 8px;
        }
        
        .company-address {
            font-size: 14px;
            color: #bdc3c7;
            line-height: 1.4;
        }
        
        .content {
            padding: 30px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 30px;
            background-color: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }
        
        th, td {
            padding: 16px 12px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }
        
        th {
            background: linear-gradient(135deg, #34495e 0%, #2c3e50 100%);
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 13px;
            letter-spacing: 0.5px;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        tbody tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        
        tbody tr:hover {
            background-color: #e3f2fd;
            transition: background-color 0.2s ease;
        }
        
        .category-header {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            font-weight: bold;
            font-size: 16px;
            color: white;
        }
        
        .category-header td {
            padding: 18px 12px;
            color: white;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .category-header:hover {
            background: linear-gradient(135deg, #2980b9 0%, #3498db 100%);
        }
        
        .sub-category {
            background-color: #f8f9fa;
            font-weight: 600;
        }
        
        .sub-category td {
            padding-left: 30px;
            color: #34495e;
        }
        
        .item-row td:first-child {
            padding-left: 50px;
        }
        
        .price {
            text-align: right;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .total-row {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
            font-weight: bold;
        }
        
        .total-row td {
            padding: 15px 12px;
            font-size: 18px;
            color: white;
        }
        
        .total-row .price {
            text-align: right;
            font-weight: bold;
            color: white;
        }
        
        .footer {
            background-color: #ecf0f1;
            padding: 20px;
            text-align: center;
            color: #7f8c8d;
            font-size: 14px;
        }
        
        .summary-box {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin: 40px 0;
        }
        
        .summary-item {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 30px;
            border-radius: 12px;
            text-align: center;
            border: 1px solid #dee2e6;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .summary-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #3498db, #2ecc71);
        }
        
        .summary-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
        }
        
        .summary-item h3 {
            color: #2c3e50;
            margin-bottom: 15px;
            font-size: 18px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .summary-item .amount {
            font-size: 28px;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 8px;
        }
        
        .summary-item .percentage {
            color: #6c757d;
            font-size: 16px;
            font-weight: 500;
        }
        
        .summary-item:first-child .amount {
            color: #e74c3c;
        }
        
        .summary-item:last-child .amount {
            color: #27ae60;
        }
        
        .pdf-button {
            position: fixed;
            top: 20px;
            right: 20px;
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
            border: none;
            padding: 15px 25px;
            border-radius: 50px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(231, 76, 60, 0.3);
            transition: all 0.3s ease;
            z-index: 1000;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .pdf-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(231, 76, 60, 0.4);
            background: linear-gradient(135deg, #c0392b 0%, #e74c3c 100%);
        }
        
        .pdf-button:active {
            transform: translateY(0);
        }
        
        .pdf-button.generating {
            background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%);
            cursor: not-allowed;
        }
        
        .pdf-icon {
            width: 20px;
            height: 20px;
        }
        
        @media print {
            body {
                background-color: white;
                padding: 0;
            }
            
            .container {
                box-shadow: none;
            }
        }
        
        @media (max-width: 768px) {
            body {
                padding: 10px;
            }
            
            .pdf-button {
                position: relative;
                top: auto;
                right: auto;
                margin: 10px auto 20px auto;
                display: flex;
                justify-content: center;
            }
            
            .header {
                padding: 30px 20px;
            }
            
            .header h1 {
                font-size: 24px;
            }
            
            .company-name {
                font-size: 22px;
            }
            
            .content {
                padding: 20px;
            }
            
            .summary-box {
                grid-template-columns: 1fr;
                gap: 20px;
                margin: 30px 0;
            }
            
            .summary-item {
                padding: 20px;
            }
            
            table {
                font-size: 14px;
            }
            
            th, td {
                padding: 10px 8px;
            }
            
            .item-row td:first-child {
                padding-left: 20px;
            }
            
            .sub-category td {
                padding-left: 15px;
            }
        }
    </style>
</head>
<body>
    <!-- Bootstrap CSS untuk navbar -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary" style="position: fixed; top: 0; width: 100%; z-index: 1000;">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php"><i class="fas fa-chart-line"></i> Smart Marketing Agent</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="dashboard.php">Dashboard</a>
                <a class="nav-link active" href="budget.php">Budget Plan</a>
                <a class="nav-link" href="analysis.php">RFM Analysis</a>
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle"></i> <?= htmlspecialchars($user['full_name']) ?>
                    </a>
                    <ul class="dropdown-menu">
                        <li><span class="dropdown-item-text text-muted"><?= htmlspecialchars($business['business_name']) ?></span></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>
    
    <!-- Spacer untuk navbar fixed -->
    <div style="height: 70px;"></div>
    
    <!-- PDF Generate Button -->
    <button class="pdf-button" onclick="generatePDF()" id="pdfButton">
        <svg class="pdf-icon" fill="currentColor" viewBox="0 0 24 24">
            <path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/>
        </svg>
        Generate PDF
    </button>
    
    <div class="container" id="content-to-pdf">
        <div class="header">
            <h1>Rencana Anggaran Biaya</h1>
            <p class="subtitle">Smart Marketing Agent Untuk Analysis RFM Customer</p>
            
            <div class="company-info">
                <div class="company-name">SJM Software House</div>
                <div class="company-address">
                    Jl. Proton Raya AB8 No.4<br>
                    Ngaliyan, Semarang, Jawa Tengah 50185
                </div>
            </div>
        </div>
        
        <div class="content">
            <div class="summary-box">
                <div class="summary-item">
                    <h3>INFRASTRUCTURE</h3>
                    <div class="amount">Rp 48.000.000</div>
                    <div class="percentage">60%</div>
                </div>
                <div class="summary-item">
                    <h3>TIM TEKNIS</h3>
                    <div class="amount">Rp 32.000.000</div>
                    <div class="percentage">40%</div>
                </div>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th style="width: 5%">No</th>
                        <th style="width: 35%">Item</th>
                        <th style="width: 25%">Provider/Keterangan</th>
                        <th style="width: 15%">Duration</th>
                        <th style="width: 20%">Biaya (Rp)</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- INFRASTRUCTURE -->
                    <tr class="category-header">
                        <td>A</td>
                        <td colspan="3">INFRASTRUCTURE</td>
                        <td class="price">48.000.000</td>
                    </tr>
                    
                    <!-- Server & Hosting -->
                    <tr class="sub-category">
                        <td>1</td>
                        <td colspan="3">Server & Hosting</td>
                        <td class="price">11.400.000</td>
                    </tr>
                    <tr class="item-row">
                        <td></td>
                        <td>VPS Production</td>
                        <td>DigitalOcean</td>
                        <td>12 bulan</td>
                        <td class="price">7.200.000</td>
                    </tr>
                    <tr class="item-row">
                        <td></td>
                        <td>Vercel Pro</td>
                        <td>Vercel</td>
                        <td>12 bulan</td>
                        <td class="price">3.600.000</td>
                    </tr>
                    <tr class="item-row">
                        <td></td>
                        <td>Domain & SSL</td>
                        <td>Namecheap</td>
                        <td>12 bulan</td>
                        <td class="price">600.000</td>
                    </tr>
                    
                    <!-- Database Services -->
                    <tr class="sub-category">
                        <td>2</td>
                        <td colspan="3">Database Services</td>
                        <td class="price">8.400.000</td>
                    </tr>
                    <tr class="item-row">
                        <td></td>
                        <td>Supabase Pro</td>
                        <td>Supabase</td>
                        <td>12 bulan</td>
                        <td class="price">3.600.000</td>
                    </tr>
                    <tr class="item-row">
                        <td></td>
                        <td>PostgreSQL Managed</td>
                        <td>Railway/Neon</td>
                        <td>12 bulan</td>
                        <td class="price">4.800.000</td>
                    </tr>
                    
                    <!-- API Services -->
                    <tr class="sub-category">
                        <td>3</td>
                        <td colspan="3">API Services</td>
                        <td class="price">24.000.000</td>
                    </tr>
                    <tr class="item-row">
                        <td></td>
                        <td>OpenAI GPT-4 Credits</td>
                        <td>OpenAI</td>
                        <td>3M tokens/month</td>
                        <td class="price">14.400.000</td>
                    </tr>
                    <tr class="item-row">
                        <td></td>
                        <td>WhatsApp Business API</td>
                        <td>Fonnte Premium</td>
                        <td>12 bulan</td>
                        <td class="price">6.000.000</td>
                    </tr>
                    <tr class="item-row">
                        <td></td>
                        <td>Email Service</td>
                        <td>Resend Pro</td>
                        <td>12 bulan</td>
                        <td class="price">3.600.000</td>
                    </tr>
                    
                    <!-- Development Tools -->
                    <tr class="sub-category">
                        <td>4</td>
                        <td colspan="3">Development Tools</td>
                        <td class="price">4.200.000</td>
                    </tr>
                    <tr class="item-row">
                        <td></td>
                        <td>GitHub Team</td>
                        <td>Version Control</td>
                        <td>12 bulan</td>
                        <td class="price">1.200.000</td>
                    </tr>
                    <tr class="item-row">
                        <td></td>
                        <td>Sentry Pro</td>
                        <td>Error Tracking</td>
                        <td>12 bulan</td>
                        <td class="price">1.800.000</td>
                    </tr>
                    <tr class="item-row">
                        <td></td>
                        <td>Vercel Analytics</td>
                        <td>Analytics</td>
                        <td>12 bulan</td>
                        <td class="price">1.200.000</td>
                    </tr>
                    
                    <!-- TIM TEKNIS -->
                    <tr class="category-header">
                        <td>B</td>
                        <td colspan="3">TIM TEKNIS</td>
                        <td class="price">32.000.000</td>
                    </tr>
                    
                    <!-- Development Team -->
                    <tr class="sub-category">
                        <td>5</td>
                        <td colspan="3">Development Team</td>
                        <td class="price">24.000.000</td>
                    </tr>
                    <tr class="item-row">
                        <td></td>
                        <td>Lead Full-Stack Developer</td>
                        <td>Senior Level</td>
                        <td>3 bulan</td>
                        <td class="price">24.000.000</td>
                    </tr>
                    
                    <!-- Design -->
                    <tr class="sub-category">
                        <td>6</td>
                        <td colspan="3">Design</td>
                        <td class="price">6.000.000</td>
                    </tr>
                    <tr class="item-row">
                        <td></td>
                        <td>UI/UX Designer</td>
                        <td>Mid-Senior Level</td>
                        <td>1.5 bulan</td>
                        <td class="price">6.000.000</td>
                    </tr>
                    
                    <!-- Quality Assurance -->
                    <tr class="sub-category">
                        <td>7</td>
                        <td colspan="3">Quality Assurance</td>
                        <td class="price">2.000.000</td>
                    </tr>
                    <tr class="item-row">
                        <td></td>
                        <td>QA Tester</td>
                        <td>Mid Level</td>
                        <td>1 bulan</td>
                        <td class="price">2.000.000</td>
                    </tr>
                    
                    <!-- GRAND TOTAL -->
                    <tr class="total-row">
                        <td colspan="4">GRAND TOTAL</td>
                        <td class="price">80.000.000</td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <div class="footer">
            <p><strong>SJM Software House</strong></p>
            <p>Jl. Proton Raya AB8 No.4, Ngaliyan, Semarang, Jawa Tengah 50185</p>
        </div>
    </div>

    <script>
        async function generatePDF() {
            const button = document.getElementById('pdfButton');
            const originalText = button.innerHTML;
            
            // Show loading state
            button.innerHTML = `
                <svg class="pdf-icon" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M12,4V2A10,10 0 0,0 2,12H4A8,8 0 0,1 12,4Z">
                        <animateTransform attributeName="transform" attributeType="XML" type="rotate" dur="1s" from="0 12 12" to="360 12 12" repeatCount="indefinite"/>
                    </path>
                </svg>
                Generating...
            `;
            button.classList.add('generating');
            button.disabled = true;

            try {
                // Hide the PDF button temporarily
                button.style.display = 'none';

                const element = document.getElementById('content-to-pdf');
                
                // Configure html2canvas options for better quality
                const canvas = await html2canvas(element, {
                    scale: 2,
                    useCORS: true,
                    allowTaint: true,
                    backgroundColor: '#ffffff',
                    width: element.scrollWidth,
                    height: element.scrollHeight,
                    scrollX: 0,
                    scrollY: 0
                });

                // Calculate PDF dimensions
                const imgWidth = 210; // A4 width in mm
                const pageHeight = 295; // A4 height in mm
                const imgHeight = (canvas.height * imgWidth) / canvas.width;
                let heightLeft = imgHeight;

                const { jsPDF } = window.jspdf;
                const pdf = new jsPDF('p', 'mm', 'a4');
                
                let position = 0;

                // Add first page
                pdf.addImage(canvas.toDataURL('image/png'), 'PNG', 0, position, imgWidth, imgHeight);
                heightLeft -= pageHeight;

                // Add additional pages if needed
                while (heightLeft >= 0) {
                    position = heightLeft - imgHeight;
                    pdf.addPage();
                    pdf.addImage(canvas.toDataURL('image/png'), 'PNG', 0, position, imgWidth, imgHeight);
                    heightLeft -= pageHeight;
                }

                // Generate filename with current date
                const now = new Date();
                const filename = `Budget_Smart_Marketing_Agent_${now.getFullYear()}-${(now.getMonth()+1).toString().padStart(2,'0')}-${now.getDate().toString().padStart(2,'0')}.pdf`;
                
                // Save the PDF
                pdf.save(filename);

            } catch (error) {
                console.error('Error generating PDF:', error);
                alert('Terjadi kesalahan saat membuat PDF. Silakan coba lagi.');
            } finally {
                // Show button again and restore original state
                button.style.display = 'flex';
                button.innerHTML = originalText;
                button.classList.remove('generating');
                button.disabled = false;
            }
        }

        // Add print styles for better PDF quality
        const printStyles = `
            @media print {
                .pdf-button { display: none !important; }
                body { margin: 0; padding: 0; background: white; }
                .container { box-shadow: none; margin: 0; border-radius: 0; }
            }
        `;
        
        const styleSheet = document.createElement('style');
        styleSheet.textContent = printStyles;
        document.head.appendChild(styleSheet);
    </script>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>