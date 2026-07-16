<?php
session_start();
if (!isset($_SESSION['operator_id'])) {
    header("Location: ../index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>New Sale - Pharmacy System</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<nav>
    <h2>💊 Pharmacy System — Billing</h2>
    <div>
        <?php if ($_SESSION['role'] === 'Admin'): ?>
        <a href="../admin/dashboard.php">Dashboard</a>
        <?php endif; ?>
        <a href="../logout.php">Logout</a>
    </div>
</nav>

<div class="container billing-container">

    <div class="card">
        <h3>🔍 Search Medicine</h3>
        <div class="search-wrap">
            <input type="text" id="medicineSearch" placeholder="Type medicine name (e.g. para)..." autocomplete="off">
            <div class="search-results" id="searchResults"></div>
        </div>
    </div>

    <div class="card">
        <h3>🛒 Current Bill</h3>
        <table id="cartTable">
            <thead>
                <tr>
                    <th>Medicine</th>
                    <th>Price</th>
                    <th>Qty</th>
                    <th>Subtotal</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="cartBody">
                <tr id="emptyRow"><td colspan="5" class="empty-cart">No items added yet. Search and click a medicine above.</td></tr>
            </tbody>
        </table>

        <div style="margin-top:20px; border-top:1px solid #eee; padding-top:15px;">
            <div class="totals-row">
                <span>Subtotal</span>
                <span id="subtotalDisplay">Rs. 0.00</span>
            </div>
            <div class="totals-row">
                <span>Discount (Rs.)</span>
                <span><input type="number" id="discountInput" value="0" min="0" step="0.01" style="width:100px; padding:5px; border:1px solid #ccc; border-radius:4px;"></span>
            </div>
            <div class="totals-row grand">
                <span>Total</span>
                <span id="totalDisplay">Rs. 0.00</span>
            </div>
        </div>
    </div>

    <div class="card">
        <h3>💳 Payment Details</h3>
        <div class="form-row">
            <div class="form-group">
                <label>Customer Name (Optional)</label>
                <input type="text" id="customerName" placeholder="Walk-in customer">
            </div>
            <div class="form-group">
                <label>Payment Method</label>
                <select id="paymentMethod">
                    <option value="Cash">Cash</option>
                    <option value="Bank Transfer">Bank Transfer</option>
                    <option value="Easypaisa">Easypaisa</option>
                    <option value="JazzCash">JazzCash</option>
                </select>
            </div>
        </div>
        <button class="btn-checkout" id="checkoutBtn" disabled>Complete Sale</button>
    </div>

</div>

<script>
let cart = [];

const searchInput = document.getElementById('medicineSearch');
const resultsBox = document.getElementById('searchResults');
let searchTimeout;

searchInput.addEventListener('input', function() {
    const term = this.value.trim();
    clearTimeout(searchTimeout);

    if (term.length < 1) {
        resultsBox.style.display = 'none';
        return;
    }

    searchTimeout = setTimeout(() => {
        fetch('search_medicine.php?term=' + encodeURIComponent(term))
            .then(res => res.json())
            .then(data => {
                resultsBox.innerHTML = '';
                if (data.length === 0) {
                    resultsBox.innerHTML = '<div class="no-result">No matching medicine found</div>';
                } else {
                    data.forEach(med => {
                        const div = document.createElement('div');
                        div.innerHTML = `<strong>${med.medicine_name}</strong> <span class="meta">(${med.category} — Rs. ${parseFloat(med.price).toFixed(2)} — Stock: ${med.quantity})</span>`;
                        div.onclick = () => addToCart(med);
                        resultsBox.appendChild(div);
                    });
                }
                resultsBox.style.display = 'block';
            });
    }, 250);
});

document.addEventListener('click', function(e) {
    if (!e.target.closest('.search-wrap')) {
        resultsBox.style.display = 'none';
    }
});

function addToCart(med) {
    const existing = cart.find(item => item.id === med.id);
    if (existing) {
        if (existing.qty < med.quantity) {
            existing.qty += 1;
        } else {
            alert('Cannot add more. Only ' + med.quantity + ' in stock.');
        }
    } else {
        cart.push({
            id: med.id,
            name: med.medicine_name,
            price: parseFloat(med.price),
            stock: med.quantity,
            qty: 1
        });
    }
    searchInput.value = '';
    resultsBox.style.display = 'none';
    renderCart();
}

function removeFromCart(id) {
    cart = cart.filter(item => item.id !== id);
    renderCart();
}

function updateQty(id, newQty) {
    const item = cart.find(i => i.id === id);
    if (!item) return;
    newQty = parseInt(newQty);
    if (isNaN(newQty) || newQty < 1) newQty = 1;
    if (newQty > item.stock) {
        alert('Only ' + item.stock + ' in stock.');
        newQty = item.stock;
    }
    item.qty = newQty;
    renderCart();
}

function renderCart() {
    const cartBody = document.getElementById('cartBody');
    const checkoutBtn = document.getElementById('checkoutBtn');

    if (cart.length === 0) {
        cartBody.innerHTML = '<tr id="emptyRow"><td colspan="5" class="empty-cart">No items added yet. Search and click a medicine above.</td></tr>';
        checkoutBtn.disabled = true;
    } else {
        cartBody.innerHTML = '';
        cart.forEach(item => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${item.name}</td>
                <td>Rs. ${item.price.toFixed(2)}</td>
                <td><input type="number" class="qty-input" value="${item.qty}" min="1" max="${item.stock}" onchange="updateQty(${item.id}, this.value)"></td>
                <td>Rs. ${(item.price * item.qty).toFixed(2)}</td>
                <td><button class="remove-btn" onclick="removeFromCart(${item.id})">Remove</button></td>
            `;
            cartBody.appendChild(row);
        });
        checkoutBtn.disabled = false;
    }
    updateTotals();
}

function updateTotals() {
    const subtotal = cart.reduce((sum, item) => sum + (item.price * item.qty), 0);
    const discount = parseFloat(document.getElementById('discountInput').value) || 0;
    const total = Math.max(0, subtotal - discount);

    document.getElementById('subtotalDisplay').textContent = 'Rs. ' + subtotal.toFixed(2);
    document.getElementById('totalDisplay').textContent = 'Rs. ' + total.toFixed(2);
}

document.getElementById('discountInput').addEventListener('input', updateTotals);

document.getElementById('checkoutBtn').addEventListener('click', function() {
    if (cart.length === 0) return;

    const payload = {
        items: cart,
        discount: parseFloat(document.getElementById('discountInput').value) || 0,
        payment_method: document.getElementById('paymentMethod').value,
        customer_name: document.getElementById('customerName').value
    };

    this.disabled = true;
    this.textContent = 'Processing...';

    fetch('process_sale.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            window.location.href = 'receipt.php?id=' + data.sale_id;
        } else {
            alert('Error: ' + data.message);
            this.disabled = false;
            this.textContent = 'Complete Sale';
        }
    })
    .catch(err => {
        alert('Something went wrong. Please try again.');
        this.disabled = false;
        this.textContent = 'Complete Sale';
    });
});
</script>

</body>
</html>
