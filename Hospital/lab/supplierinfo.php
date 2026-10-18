<?php
session_start();
include "includes/db_connect.php";
include "includes/sidebar.php";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Supplier - AuraCare HMS</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #f8f9fa;
        }

        .main-content {
            margin-left: 250px;
            padding: 2rem;
            min-height: 100vh;
        }

        /* Header Styling */
        .page-header {
            display: flex;
            align-items: center;
            margin-bottom: 2rem;
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            color: #64748b;
            text-decoration: none;
            font-size: 1.5rem;
            margin-right: 1.5rem;
            transition: color 0.2s;
        }

        .header-text h1 {
            font-size: 1.875rem;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 0.25rem;
        }

        .header-text p {
            color: #64748b;
            font-size: 0.875rem;
        }
        
        /* Form Container */
        .form-container {
            background: white;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            max-width: 1200px;
        }

        /* Tab Navigation */
        .tab-navigation {
            display: flex;
            border-bottom: 1px solid #e2e8f0;
            padding: 0 1.5rem;
            gap: 2rem;
        }

        .tab-button {
            padding: 0.75rem 0;
            background: none;
            border: none;
            color: #64748b;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            position: relative;
            transition: color 0.2s;
        }

        .tab-button.active {
            color: #0f172a;
            font-weight: 600;
        }

        .tab-button.active::after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 0;
            right: 0;
            height: 2px;
            background: #3b82f6;
        }

        /* Tab Content */
        .tab-content {
            display: none;
            padding: 2rem 1.5rem;
        }

        .tab-content.active {
            display: block;
        }

        /* Section Header */
        .tab-section-header {
            margin-top: 2rem;
            margin-bottom: 1rem;
        }

        .tab-section-header:first-of-type {
            margin-top: 0;
        }

        .tab-section-header h2 {
            font-size: 1.25rem;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 0.25rem;
        }

        .tab-section-header p {
            color: #64748b;
            font-size: 0.875rem;
        }

        /* Form Grid */
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-grid-3-col {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .form-grid-2-col {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        /* Form Elements */
        .form-label {
            font-size: 0.875rem;
            font-weight: 500;
            color: #374151;
            margin-bottom: 0.5rem;
        }
        
        .form-input, .form-select, .form-textarea {
            padding: 0.75rem 0.875rem;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            font-size: 0.9rem;
            color: #1f2937;
            transition: all 0.2s;
            font-family: inherit;
        }

        .form-textarea {
            resize: vertical;
            min-height: 80px;
        }

        /* Radio Group */
        .radio-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            margin-top: 0.5rem;
            margin-bottom: 1.5rem;
        }

        .radio-label {
            font-size: 0.9rem;
            font-weight: 400;
            color: #1f2937;
            cursor: pointer;
            display: flex;
            align-items: center;
        }

        .radio-label input[type="radio"] {
            margin-right: 0.5rem;
            accent-color: #3b82f6;
        }

        /* Payment Method Cards */
        .payment-methods {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .payment-method-card {
            border: 2px solid #e2e8f0;
            border-radius: 0.75rem;
            padding: 1.5rem;
            cursor: pointer;
            transition: all 0.2s;
            background: white;
        }

        .payment-method-card:hover {
            border-color: #3b82f6;
        }

        .payment-method-card.selected {
            border-color: #3b82f6;
            background-color: #eff6ff;
        }

        .payment-method-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 0.5rem;
        }

        .payment-method-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            color: white;
        }

        .bank-icon { background-color: #10b981; }
        .mpesa-icon { background-color: #06c755; }
        .cash-icon { background-color: #6b7280; }
        .card-icon { background-color: #3b82f6; }

        .payment-method-title {
            font-weight: 600;
            color: #1f2937;
            margin: 0;
        }

        .payment-method-description {
            font-size: 0.875rem;
            color: #64748b;
            margin: 0;
        }

        /* Payment Details */
        .payment-details {
            display: none;
            background: #f8fafc;
            border-radius: 0.5rem;
            padding: 1.5rem;
            margin-top: 1rem;
            border: 1px solid #e2e8f0;
        }

        .payment-details.active {
            display: block;
        }

        /* Form Navigation */
        .form-navigation {
            position: sticky;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            border-top: 1px solid #e2e8f0;
            padding: 1rem 2rem;
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
            box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.05);
            margin-left: -250px;
            width: calc(100% + 250px);
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            font-weight: 500;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-secondary {
            background: white;
            border: 1px solid #e2e8f0;
            color: #64748b;
        }

        .btn-primary {
            background-color: #0f172a;
            color: white;
        }

        .btn-primary:hover {
            background-color: #1e293b;
        }
        
        /* Responsive */
        @media (max-width: 1024px) {
            .form-grid-3-col {
                grid-template-columns: repeat(2, 1fr);
            }
            .payment-methods {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding-bottom: 6rem;
            }
            .form-grid-3-col, .form-grid-2-col {
                grid-template-columns: 1fr;
            }
            .payment-methods {
                grid-template-columns: 1fr;
            }
            .form-navigation {
                width: 100%;
                margin-left: 0;
                padding: 1rem;
                justify-content: space-between;
            }
        }
    </style>
</head>
<body>

<div class="main-content">
    <div class="page-header">
        <a href="#" onclick="history.back(); return false;" class="back-button">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div class="header-text">
            <h1>Add Supplier</h1>
            <p>Register a new supplier to the system.</p>
        </div>
    </div>

    <form id="supplierForm" action="supplier.php" method="POST" enctype="multipart/form-data">
        <div class="form-container">
            <div class="tab-navigation">
                <button type="button" class="tab-button active" data-tab="company">
                    Company Information
                </button>
                <button type="button" class="tab-button" data-tab="products">
                    Products & Services
                </button>
                <button type="button" class="tab-button" data-tab="purchase">
                    Purchase & Payment
                </button>
            </div>

            <!-- Company Information Tab -->
            <div class="tab-content active" id="company-tab">
                <div class="tab-section-header">
                    <h2>Company Information</h2>
                    <p>Enter the supplier's business details.</p>
                </div>

                <div class="form-grid-2-col">
                    <div class="form-group">
                        <label class="form-label">Company Name <span class="required">*</span></label>
                        <input type="text" name="company_name" class="form-input" placeholder="Enter the official business/company name" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Company Type <span class="required">*</span></label>
                        <select name="company_type" class="form-select" required>
                            <option value="">Select company type</option>
                            <option value="pharmaceutical">Pharmaceutical</option>
                            <option value="medical_supplies">Medical Supplies</option>
                            <option value="medical_equipment">Medical Equipment</option>
                            <option value="office_supplies">Office Supplies</option>
                            <option value="laboratory">Laboratory Supplies</option>
                            <option value="manufacturer">Manufacturer</option>
                            <option value="distributor">Distributor</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                </div>

                <div class="form-grid-3-col">
                    <div class="form-group">
                        <label class="form-label">Contact First Name <span class="required">*</span></label>
                        <input type="text" name="contact_first_name" class="form-input" placeholder="Enter contact person's first name" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Contact Last Name <span class="required">*</span></label>
                        <input type="text" name="contact_last_name" class="form-input" placeholder="Enter contact person's last name" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Contact Title</label>
                        <input type="text" name="contact_title" class="form-input" placeholder="e.g., Sales Manager, CEO">
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label class="form-label">Business Address <span class="required">*</span></label>
                    <textarea name="business_address" class="form-input form-textarea" placeholder="Enter the business' physical address" required></textarea>
                </div>

                <div class="tab-section-header">
                    <h2>Contact Information</h2>
                </div>

                <div class="form-grid-3-col">
                    <div class="form-group">
                        <label class="form-label">Phone Number <span class="required">*</span></label>
                        <input type="tel" name="phone" class="form-input" placeholder="Enter primary phone number" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email <span class="required">*</span></label>
                        <input type="email" name="email" class="form-input" placeholder="Enter business email address" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Website</label>
                        <input type="url" name="website" class="form-input" placeholder="https://example.com">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Preferred Contact Method</label>
                    <div class="radio-group">
                        <label class="radio-label">
                            <input type="radio" name="preferred_contact" value="phone" checked> Phone
                        </label>
                        <label class="radio-label">
                            <input type="radio" name="preferred_contact" value="email"> Email
                        </label>
                        <label class="radio-label">
                            <input type="radio" name="preferred_contact" value="both"> Both
                        </label>
                    </div>
                </div>

                <!-- <div class="tab-section-header">
                    <h2>Business Registration</h2>
                </div>

                <div class="form-grid-2-col">
                    <div class="form-group">
                        <label class="form-label">Tax ID / VAT Number</label>
                        <input type="text" name="tax_id" class="form-input" placeholder="Enter tax identification number">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Business Registration Number</label>
                        <input type="text" name="registration_number" class="form-input" placeholder="Enter business registration number">
                    </div>
                </div> -->

                <div class="tab-section-header">
                    <h2>Additional Information</h2>
                </div>

                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label class="form-label">Company Description</label>
                    <textarea name="company_description" class="form-input form-textarea" placeholder="Brief description of the company and services"></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Supplier Status</label>
                    <select name="supplier_status" class="form-select">
                        <option value="active">Active</option>
                        <option value="pending">Pending</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>

            <!-- Products & Services Tab -->
            <div class="tab-content" id="products-tab">
                <div class="tab-section-header">
                    <h2>Product Information</h2>
                    <p>Enter the details of products supplied.</p>
                </div>

                <div class="form-grid-3-col">
                    <div class="form-group">
                        <label class="form-label">Primary Product Category <span class="required">*</span></label>
                        <select name="product_category" class="form-select" required>
                            <option value="">Select category</option>
                            <option value="medications">Medications</option>
                            <option value="medical_supplies">Medical Supplies</option>
                            <option value="medical_equipment">Medical Equipment</option>
                            <option value="laboratory">Laboratory Supplies</option>
                            <option value="surgical">Surgical Instruments</option>
                            <option value="office">Office Supplies</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Product Type</label>
                        <select name="product_type" class="form-select">
                            <option value="">Select type</option>
                            <option value="disposable">Disposable</option>
                            <option value="reusable">Reusable</option>
                            <option value="prescription">Prescription</option>
                            <option value="otc">Over-The-Counter</option>
                            <option value="equipment">Equipment</option>
                            <option value="consumable">Consumable</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Unit of Measurement</label>
                        <select name="unit_of_measurement" class="form-select">
                            <option value="">Select unit</option>
                            <option value="each">Each</option>
                            <option value="box">Box</option>
                            <option value="pack">Pack</option>
                            <option value="bottle">Bottle</option>
                            <option value="tube">Tube</option>
                            <option value="case">Case</option>
                            <option value="kg">Kilogram</option>
                            <option value="liter">Liter</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label class="form-label">Product Description</label>
                    <textarea name="product_description" class="form-input form-textarea" placeholder="Describe the main products supplied"></textarea>
                </div>

                <div class="tab-section-header">
                    <h2>Product Specifications</h2>
                </div>

                <div class="form-grid-2-col">
                    <div class="form-group">
                        <label class="form-label">Brand Names</label>
                        <input type="text" name="brand_names" class="form-input" placeholder="Enter brand names supplied">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Manufacturer</label>
                        <input type="text" name="manufacturer" class="form-input" placeholder="Enter manufacturer name">
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label class="form-label">Specializations</label>
                    <textarea name="specializations" class="form-input form-textarea" placeholder="List any specializations or unique products"></textarea>
                </div>

                <div class="tab-section-header">
                    <h2>Quality & Certifications</h2>
                </div>

                <div class="form-grid-2-col">
                    <div class="form-group">
                        <label class="form-label">Quality Certifications</label>
                        <input type="text" name="quality_certifications" class="form-input" placeholder="e.g., ISO, FDA, CE Mark">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Expiry Date Handling</label>
                        <select name="expiry_handling" class="form-select">
                            <option value="">Select policy</option>
                            <option value="exchange">Exchange near-expiry items</option>
                            <option value="return">Return near-expiry items</option>
                            <option value="discount">Discount near-expiry items</option>
                            <option value="none">No specific policy</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Purchase & Payment Tab -->
            <div class="tab-content" id="purchase-tab">
                <div class="tab-section-header">
                    <h2>Purchase Terms</h2>
                    <p>Define purchasing and payment terms.</p>
                </div>

                <div class="form-grid-3-col">
                    <div class="form-group">
                        <label class="form-label">Minimum Order Quantity</label>
                        <input type="number" name="min_order_quantity" class="form-input" placeholder="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Lead Time (Days)</label>
                        <input type="number" name="lead_time" class="form-input" placeholder="Average delivery time">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Delivery Method</label>
                        <select name="delivery_method" class="form-select">
                            <option value="">Select method</option>
                            <option value="pickup">Customer Pickup</option>
                            <option value="delivery">Supplier Delivery</option>
                            <option value="courier">Courier Service</option>
                            <option value="both">Both Available</option>
                        </select>
                    </div>
                </div>

                <div class="tab-section-header">
                    <h2>Payment Method</h2>
                    <p>Select preferred payment methods.</p>
                    <input type="hidden" name="payment_method" id="payment_method" value="">
                </div>
                    
                <div class="payment-methods">
                    <!-- Bank Transfer -->
                    <div class="payment-method-card" data-method="bank_transfer">
                        <div class="payment-method-header">
                            <div class="payment-method-icon bank-icon">
                                <i class="fas fa-university"></i>
                            </div>
                            <div>
                                <h3 class="payment-method-title">Bank Transfer</h3>
                                <p class="payment-method-description">Direct bank transfer</p>
                            </div>
                        </div>
                    </div>

                    <!-- M-Pesa -->
                    <div class="payment-method-card" data-method="mpesa">
                        <div class="payment-method-header">
                            <div class="payment-method-icon mpesa-icon">
                                <i class="fas fa-mobile-alt"></i>
                            </div>
                            <div>
                                <h3 class="payment-method-title">M-Pesa</h3>
                                <p class="payment-method-description">Mobile money payment</p>
                            </div>
                        </div>
                    </div>

                    <!-- Cash -->
                    <div class="payment-method-card" data-method="cash">
                        <div class="payment-method-header">
                            <div class="payment-method-icon cash-icon">
                                <i class="fas fa-money-bill-wave"></i>
                            </div>
                            <div>
                                <h3 class="payment-method-title">Cash</h3>
                                <p class="payment-method-description">Cash on delivery</p>
                            </div>
                        </div>
                    </div>

                    <!-- Credit Card -->
                    <div class="payment-method-card" data-method="credit_card">
                        <div class="payment-method-header">
                            <div class="payment-method-icon card-icon">
                                <i class="fas fa-credit-card"></i>
                            </div>
                            <div>
                                <h3 class="payment-method-title">Credit Card</h3>
                                <p class="payment-method-description">Credit/Debit card</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bank Transfer Details -->
                <div class="payment-details" id="bank_transfer-details">
                    <h3 style="margin-bottom: 1rem; color: #0f172a;">Bank Transfer Details</h3>
                    <div class="form-grid-2-col">
                        <div class="form-group">
                            <label class="form-label">Bank Name</label>
                            <input type="text" name="bank_name" class="form-input" placeholder="Enter bank name">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Account Name</label>
                            <input type="text" name="account_name" class="form-input" placeholder="Enter account name">
                        </div>
                    </div>
                    <div class="form-grid-2-col">
                        <div class="form-group">
                            <label class="form-label">Account Number</label>
                            <input type="text" name="account_number" class="form-input" placeholder="Enter account number">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Branch</label>
                            <input type="text" name="branch" class="form-input" placeholder="Enter branch name">
                        </div>
                    </div>
                </div>

                <!-- M-Pesa Details -->
                <div class="payment-details" id="mpesa-details">
                    <h3 style="margin-bottom: 1rem; color: #0f172a;">M-Pesa Payment Details</h3>
                    <div class="form-grid-2-col">
                        <div class="form-group">
                            <label class="form-label">PayBill Number</label>
                            <input type="text" name="paybill_number" class="form-input" placeholder="Enter PayBill number">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Account Number</label>
                            <input type="text" name="mpesa_account" class="form-input" placeholder="Enter account number">
                        </div>
                    </div>
                </div>

                <!-- Credit Card Details -->
                <div class="payment-details" id="credit_card-details">
                    <h3 style="margin-bottom: 1rem; color: #0f172a;">Credit Card Details</h3>
                    <div class="form-grid-2-col">
                        <div class="form-group">
                            <label class="form-label">Card Type</label>
                            <select name="card_type" class="form-select">
                                <option value="">Select card type</option>
                                <option value="visa">Visa</option>
                                <option value="mastercard">MasterCard</option>
                                <option value="amex">American Express</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Processing Fee (%)</label>
                            <input type="number" name="card_fee" class="form-input" placeholder="0" step="0.01">
                        </div>
                    </div>
                </div>

                <!-- Cash Details -->
                <div class="payment-details" id="cash-details">
                    <h3 style="margin-bottom: 1rem; color: #0f172a;">Cash Payment</h3>
                    <p style="color: #64748b; margin-bottom: 1.5rem;">Cash payments accepted at delivery or pickup.</p>
                    <div class="form-group">
                        <label class="form-label">Maximum Cash Amount</label>
                        <input type="number" name="max_cash_amount" class="form-input" placeholder="Enter maximum cash amount accepted">
                    </div>
                </div>

                <div class="tab-section-header">
                    <h2>Credit Terms</h2>
                </div>

                <div class="form-grid-2-col">
                    <div class="form-group">
                        <label class="form-label">Credit Limit</label>
                        <input type="number" name="credit_limit" class="form-input" placeholder="Enter credit limit amount">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Payment Terms (Days)</label>
                        <input type="number" name="payment_terms" class="form-input" placeholder="e.g., 30, 60, 90">
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label class="form-label">Additional Terms & Conditions</label>
                    <textarea name="additional_terms" class="form-input form-textarea" placeholder="Enter any additional terms and conditions"></textarea>
                </div>
            </div>
        </div>

        <div class="form-navigation">
            <a href="suppliers.php" class="btn btn-secondary">Cancel</a>
            
            <div id="step-buttons" style="display: flex; gap: 0.75rem;">
                <button type="button" class="btn btn-secondary" id="prevBtn" onclick="changeTab(-1)" style="display: none;">
                    <i class="fas fa-arrow-left"></i>
                    Previous
                </button>
                <button type="button" class="btn btn-primary" id="nextBtn" onclick="changeTab(1)">
                    Next: Products & Services
                    <i class="fas fa-arrow-right"></i>
                </button>
                <button type="submit" class="btn btn-primary" id="submitBtn" style="display: none;">
                    Register Supplier
                </button>
            </div>
        </div>
    </form>
</div>

<script>
let currentTab = 0;
const tabs = ['company', 'products', 'purchase'];

// Initialize
showTab(currentTab);

function showTab(n) {
    const tabContents = document.querySelectorAll('.tab-content');
    const tabButtons = document.querySelectorAll('.tab-button');
    
    // Hide all tabs
    tabContents.forEach(tab => tab.classList.remove('active'));
    tabButtons.forEach(btn => btn.classList.remove('active'));
    
    // Show current tab
    document.getElementById(tabs[n] + '-tab').classList.add('active');
    tabButtons[n].classList.add('active');
    
    // Update navigation buttons
    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');
    const submitBtn = document.getElementById('submitBtn');

    // Show/hide Previous button
    if (n > 0) {
        prevBtn.style.display = 'inline-flex';
        prevBtn.innerHTML = `<i class="fas fa-arrow-left"></i> Previous: ${tabButtons[n - 1].textContent}`;
    } else {
        prevBtn.style.display = 'none';
    }
    
    // Show/hide Next and Submit buttons
    if (n < tabs.length - 1) {
        nextBtn.style.display = 'inline-flex';
        nextBtn.innerHTML = `Next: ${tabButtons[n + 1].textContent} <i class="fas fa-arrow-right"></i>`;
        submitBtn.style.display = 'none';
    } else {
        nextBtn.style.display = 'none';
        submitBtn.style.display = 'inline-flex';
    }
    
    currentTab = n;
}

function changeTab(direction) {
    const newTab = currentTab + direction;
    
    if (validateCurrentTab()) {
        if (newTab >= 0 && newTab < tabs.length) {
            showTab(newTab);
        }
    }
}

function validateCurrentTab() {
    // Add validation logic here for each tab
    return true;
}

// Payment Method Selection
document.querySelectorAll('.payment-method-card').forEach(card => {
    card.addEventListener('click', function() {
        const method = this.getAttribute('data-method');
        document.getElementById('payment_method').value = method;
        
        // Remove selected class from all cards
        document.querySelectorAll('.payment-method-card').forEach(c => {
            c.classList.remove('selected');
        });
        
        // Add selected class to clicked card
        this.classList.add('selected');
        
        // Hide all payment details
        document.querySelectorAll('.payment-details').forEach(detail => {
            detail.classList.remove('active');
        });
        
        // Show selected payment method details
        const detailsElement = document.getElementById(method + '-details');
        if (detailsElement) {
            detailsElement.classList.add('active');
        }
    });
});

// Allow clicking on tabs
document.querySelectorAll('.tab-button').forEach((button, index) => {
    button.addEventListener('click', () => {
        if (validateCurrentTab()) {
            showTab(index);
        }
    });
});
</script>

</body>
</html>