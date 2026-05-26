// Main app logic: AJAX calls, UI controls, and Chart.js setup

// Format number as USD currency
const formatMoney = (amount) => {
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(amount);
};

// Format number with commas
const formatNumber = (num) => {
    return new Intl.NumberFormat('en-US').format(num);
};

// Chart.js chart instances
let trendChart, categoryChart, regionChart;

// Global Chart.js styling
Chart.defaults.color = '#94a3b8';
Chart.defaults.borderColor = '#334155';

document.addEventListener('DOMContentLoaded', () => {
    
    // DOM Element References
    const fileInput = document.getElementById('csv-file');
    const uploadBtn = document.getElementById('btn-upload');
    const mockBtn = document.getElementById('btn-mock');
    const resetBtn = document.getElementById('btn-reset');
    
    const progressContainer = document.getElementById('progress-container');
    const progressFill = document.getElementById('progress-fill');
    const progressText = document.getElementById('progress-text');
    const progressRows = document.getElementById('progress-rows');
    
    const dashboardContent = document.getElementById('dashboard-content');
    const searchInput = document.getElementById('search-input');
    
    // Setup Event Listeners
    
    // Proxy file input click
    uploadBtn.addEventListener('click', () => {
        fileInput.click();
    });

    // Handle file upload selection
    fileInput.addEventListener('change', async (e) => {
        const file = e.target.files[0];
        if (!file) return;
        startUpload(file);
    });

    // Trigger mock CSV generation
    mockBtn.addEventListener('click', async () => {
        mockBtn.innerHTML = '<span class="loader"></span> Generating 50,000 rows...';
        mockBtn.disabled = true;

        try {
            // Call mock API
            const response = await fetch('api.php?action=generate_mock&rows=50000');
            const data = await response.json();
            
            if (data.success) {
                // If successful, begin chunk import
                mockBtn.innerHTML = '<i class="fas fa-magic"></i> Generate Mock Data';
                mockBtn.disabled = false;

                // Initialize progress UI
                showProgress('Importing 50,000 rows into database...', '5%');
                dashboardContent.classList.add('hidden');

                processChunk(data.filepath, data.upload_id, 0, data.total_rows);
            } else {
                alert("Error generating data: " + data.error);
                mockBtn.innerHTML = '<i class="fas fa-magic"></i> Generate Mock Data';
                mockBtn.disabled = false;
            }
        } catch (err) {
            alert("Error: Could not connect to the server. Make sure Laragon is running.");
            console.error(err);
            mockBtn.innerHTML = '<i class="fas fa-magic"></i> Generate Mock Data';
            mockBtn.disabled = false;
        }
    });

    // Confirm and trigger database reset
    resetBtn.addEventListener('click', async () => {
        if (confirm("Are you sure you want to delete all data from the database?")) {
            await fetch('api.php?action=reset');
            dashboardContent.classList.add('hidden');
            alert("Database cleared.");
        }
    });

    // Debounce search input to avoid spamming requests
    let searchTimeout;
    searchInput.addEventListener('input', (e) => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            loadDataTable(0, e.target.value);
        }, 500);
    });

    // UI Progress helpers
    function showProgress(text, width) {
        progressContainer.style.display = 'block';
        progressContainer.classList.remove('hidden');
        progressText.textContent = text;
        progressFill.style.width = width;
        progressRows.textContent = '';
    }

    function hideProgress() {
        progressContainer.style.display = 'none';
        progressContainer.classList.add('hidden');
    }

    // Send CSV file to server
    async function startUpload(file) {
        const formData = new FormData();
        formData.append('csv_file', file);

        // Show progress bar
        showProgress('Uploading file to server...', '5%');
        dashboardContent.classList.add('hidden');

        try {
            const response = await fetch('api.php?action=upload', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();

            if (data.success) {
                // Start chunk import on success
                showProgress('Processing CSV data...', '10%');
                processChunk(data.filepath, data.upload_id, 0, data.total_rows);
            } else {
                alert("Upload failed: " + data.error);
                hideProgress();
            }
        } catch (err) {
            console.error(err);
            alert("Upload error: Could not connect to the server.");
            hideProgress();
        }
    }

    // Import CSV chunks recursively
    async function processChunk(filepath, uploadId, offset, totalRows) {
        try {
            const response = await fetch(`api.php?action=import_chunk&filepath=${filepath}&upload_id=${uploadId}&offset=${offset}&limit=2000`);
            const data = await response.json();

            if (data.success) {
                const newOffset = data.next_offset;
                const percent = Math.min(100, Math.round((newOffset / totalRows) * 100));
                
                progressFill.style.width = percent + "%";
                progressRows.textContent = `${formatNumber(Math.min(newOffset, totalRows))} / ${formatNumber(totalRows)} rows`;
                progressText.textContent = `Importing data... ${percent}%`;

                // Recurse if more rows remain
                if (newOffset < totalRows && data.processed > 0) {
                    // Tiny timeout to let browser UI update
                    setTimeout(() => {
                        processChunk(filepath, uploadId, newOffset, totalRows);
                    }, 50);
                } else {
                    // Cleanup and load dashboard
                    progressText.textContent = "Processing Complete!";
                    progressFill.style.width = "100%";
                    
                    setTimeout(() => {
                        hideProgress();
                        fileInput.value = '';
                        loadDashboard();
                    }, 1000);
                }
            } else {
                alert("Processing failed: " + data.error);
                hideProgress();
            }
        } catch (err) {
            console.error(err);
            alert("Chunk processing error: " + err.message);
            hideProgress();
        }
    }

    // Load analytics data and populate dashboard
    async function loadDashboard() {
        try {
            const response = await fetch('api.php?action=get_analytics');
            const data = await response.json();
            
            if (data.success && data.kpis && data.kpis.total_orders > 0) {
                dashboardContent.style.display = 'block';
                dashboardContent.classList.remove('hidden');

                // Render KPIs
                document.getElementById('kpi-revenue').textContent = formatMoney(data.kpis.total_revenue || 0);
                document.getElementById('kpi-profit').textContent = formatMoney(data.kpis.total_profit || 0);
                document.getElementById('kpi-orders').textContent = formatNumber(data.kpis.total_orders || 0);
                document.getElementById('kpi-margin').textContent = (data.kpis.profit_margin || 0) + '%';
                
                // Draw charts
                renderTrendChart(data.charts.trend);
                renderCategoryChart(data.charts.category);
                renderRegionChart(data.charts.region);
                
                // Load initial data table
                loadDataTable();
            } else {
                dashboardContent.style.display = 'none';
                dashboardContent.classList.add('hidden');
            }
        } catch (err) {
            console.error('Dashboard load error:', err);
        }
    }

    // Fetch data table rows
    async function loadDataTable(offset = 0, search = '') {
        try {
            const response = await fetch(`api.php?action=get_data&offset=${offset}&limit=50&search=${encodeURIComponent(search)}`);
            const data = await response.json();
            
            if (data.success) {
                const tbody = document.getElementById('table-body');
                tbody.innerHTML = '';
                
                if (data.data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="8" style="text-align:center; padding:2rem; color:#94a3b8;">No data found.</td></tr>';
                    return;
                }
                
                data.data.forEach(row => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td><strong>${row.order_id}</strong></td>
                        <td>${row.order_date}</td>
                        <td>${row.customer_name}</td>
                        <td>${row.product_name}</td>
                        <td><span class="badge badge-success">${row.category}</span></td>
                        <td class="text-right">${formatMoney(row.price)}</td>
                        <td class="text-right">${row.quantity}</td>
                        <td class="text-right text-success">${formatMoney(row.profit)}</td>
                    `;
                    tbody.appendChild(tr);
                });
            }
        } catch (err) {
            console.error(err);
        }
    }

    // Chart creation functions

    function renderTrendChart(data) {
        const ctx = document.getElementById('trendChart').getContext('2d');
        if (trendChart) trendChart.destroy();
        
        trendChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.map(d => d.month),
                datasets: [{
                    label: 'Revenue',
                    data: data.map(d => d.revenue),
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } }
            }
        });
    }

    function renderCategoryChart(data) {
        const ctx = document.getElementById('categoryChart').getContext('2d');
        if (categoryChart) categoryChart.destroy();
        
        categoryChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: data.map(d => d.category),
                datasets: [{
                    data: data.map(d => d.revenue),
                    backgroundColor: ['#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#ef4444'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: { position: 'right' }
                }
            }
        });
    }

    function renderRegionChart(data) {
        const ctx = document.getElementById('regionChart').getContext('2d');
        if (regionChart) regionChart.destroy();
        
        regionChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: data.map(d => d.region),
                datasets: [{
                    label: 'Profit',
                    data: data.map(d => d.profit),
                    backgroundColor: '#10b981',
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } }
            }
        });
    }
    
    // Initialize dashboard on load
    loadDashboard();
});
