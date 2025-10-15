
        // ===== WORKSHOP ANALYTICS CHARTS =====
        
        // Trainer Performance Chart
        if (document.getElementById('trainerPerformanceChart')) {
            const trainerData = <?php echo isset($analytics['trainer_performance']) ? json_encode(array_slice($analytics['trainer_performance'], 0, 8)) : '[]'; ?>;
            new Chart(document.getElementById('trainerPerformanceChart'), {
                type: 'bar',
                data: {
                    labels: trainerData.map(t => t.name),
                    datasets: [{
                        label: 'Revenue (₹)',
                        data: trainerData.map(t => parseFloat(t.total_revenue)),
                        backgroundColor: '#3498db',
                        borderRadius: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: { beginAtZero: true }
                    }
                }
            });
        }

        // Category Analysis Chart
        if (document.getElementById('categoryAnalysisChart')) {
            const categoryData = <?php echo isset($analytics['category_analysis']) ? json_encode($analytics['category_analysis']) : '[]'; ?>;
            new Chart(document.getElementById('categoryAnalysisChart'), {
                type: 'doughnut',
                data: {
                    labels: categoryData.map(c => c.category_name),
                    datasets: [{
                        data: categoryData.map(c => parseFloat(c.revenue)),
                        backgroundColor: ['#3498db', '#2ecc71', '#f39c12', '#e74c3c', '#9b59b6', '#1abc9c']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom' }
                    }
                }
            });
        }

        // ===== PAYMENT & REVENUE ANALYTICS CHARTS =====
        
        // Payment Methods Chart
        if (document.getElementById('paymentMethodsChart')) {
            const paymentMethodsData = <?php echo isset($analytics['payment_methods']) ? json_encode($analytics['payment_methods']) : '[]'; ?>;
            new Chart(document.getElementById('paymentMethodsChart'), {
                type: 'pie',
                data: {
                    labels: paymentMethodsData.map(p => p.payment_method),
                    datasets: [{
                        data: paymentMethodsData.map(p => parseInt(p.count)),
                        backgroundColor: ['#3498db', '#2ecc71', '#f39c12']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom' }
                    }
                }
            });
        }

        // Revenue Breakdown Chart
        if (document.getElementById('revenueBreakdownChart')) {
            const revenueBreakdownData = <?php echo isset($analytics['revenue_breakdown']) ? json_encode($analytics['revenue_breakdown']) : '[]'; ?>;
            new Chart(document.getElementById('revenueBreakdownChart'), {
                type: 'bar',
                data: {
                    labels: revenueBreakdownData.map(r => r.user_type),
                    datasets: [{
                        label: 'Revenue (₹)',
                        data: revenueBreakdownData.map(r => parseFloat(r.total_revenue)),
                        backgroundColor: ['#3498db', '#2ecc71'],
                        borderRadius: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: { beginAtZero: true }
                    }
                }
            });
        }

        // ===== SCHOOL & USER ANALYTICS CHARTS =====
        
        // User Engagement Chart
        if (document.getElementById('userEngagementChart')) {
            const engagementData = <?php echo isset($analytics['user_engagement']) ? json_encode($analytics['user_engagement']) : '[]'; ?>;
            const engagementLevels = {
                'High': engagementData.filter(u => u.engagement_level === 'High').length,
                'Medium': engagementData.filter(u => u.engagement_level === 'Medium').length,
                'Low': engagementData.filter(u => u.engagement_level === 'Low').length
            };
            new Chart(document.getElementById('userEngagementChart'), {
                type: 'doughnut',
                data: {
                    labels: ['High', 'Medium', 'Low'],
                    datasets: [{
                        data: [engagementLevels.High, engagementLevels.Medium, engagementLevels.Low],
                        backgroundColor: ['#2ecc71', '#f39c12', '#e74c3c']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom' }
                    }
                }
            });
        }

        // User Retention Chart
        if (document.getElementById('userRetentionChart')) {
            const retentionData = <?php echo isset($analytics['user_retention']) ? json_encode(array_slice($analytics['user_retention'], 0, 6)) : '[]'; ?>;
            new Chart(document.getElementById('userRetentionChart'), {
                type: 'line',
                data: {
                    labels: retentionData.map(r => r.registration_month),
                    datasets: [{
                        label: 'Retention Rate (%)',
                        data: retentionData.map(r => parseFloat(r.retention_rate)),
                        borderColor: '#3498db',
                        backgroundColor: 'rgba(52, 152, 219, 0.1)',
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: { beginAtZero: true, max: 100 }
                    }
                }
            });
        }

        // Geographic Performance Chart
        if (document.getElementById('geographicPerformanceChart')) {
            const geoData = <?php echo isset($analytics['geographic_performance']) ? json_encode(array_slice($analytics['geographic_performance'], 0, 10)) : '[]'; ?>;
            new Chart(document.getElementById('geographicPerformanceChart'), {
                type: 'bar',
                data: {
                    labels: geoData.map(g => g.city),
                    datasets: [{
                        label: 'Revenue (₹)',
                        data: geoData.map(g => parseFloat(g.total_revenue)),
                        backgroundColor: '#3498db',
                        borderRadius: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'y',
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        x: { beginAtZero: true }
                    }
                }
            });
        }

        // User Lifecycle Chart
        if (document.getElementById('userLifecycleChart')) {
            const lifecycleData = <?php echo isset($analytics['user_lifecycle']) ? json_encode($analytics['user_lifecycle']) : '[]'; ?>;
            new Chart(document.getElementById('userLifecycleChart'), {
                type: 'pie',
                data: {
                    labels: lifecycleData.map(l => l.lifecycle_stage),
                    datasets: [{
                        data: lifecycleData.map(l => parseInt(l.user_count)),
                        backgroundColor: ['#3498db', '#2ecc71', '#f39c12', '#e74c3c', '#9b59b6']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 9 } } }
                    }
                }
            });
        }

        // ===== ADVANCED BUSINESS INTELLIGENCE CHARTS =====
        
        // Conversion Funnel Chart
        if (document.getElementById('conversionFunnelChart')) {
            const funnelData = <?php echo isset($analytics['conversion_funnel']) ? json_encode($analytics['conversion_funnel']) : '[]'; ?>;
            new Chart(document.getElementById('conversionFunnelChart'), {
                type: 'bar',
                data: {
                    labels: funnelData.map(f => f.stage),
                    datasets: [{
                        label: 'Count',
                        data: funnelData.map(f => parseInt(f.count)),
                        backgroundColor: ['#3498db', '#2ecc71', '#f39c12', '#e74c3c'],
                        borderRadius: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: { beginAtZero: true }
                    }
                }
            });
        }

        // Market Penetration Chart
        if (document.getElementById('marketPenetrationChart')) {
            const penetrationData = <?php echo isset($analytics['market_penetration']) ? json_encode(array_slice($analytics['market_penetration'], 0, 10)) : '[]'; ?>;
            new Chart(document.getElementById('marketPenetrationChart'), {
                type: 'bar',
                data: {
                    labels: penetrationData.map(p => p.city),
                    datasets: [
                        {
                            label: 'Registered Users',
                            data: penetrationData.map(p => parseInt(p.registered_users)),
                            backgroundColor: '#3498db',
                            borderRadius: 8
                        },
                        {
                            label: 'Paying Users',
                            data: penetrationData.map(p => parseInt(p.paying_users)),
                            backgroundColor: '#2ecc71',
                            borderRadius: 8
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'top' }
                    },
                    scales: {
                        y: { beginAtZero: true }
                    }
                }
            });
        }

        // ===== REAL-TIME MONITORING CHARTS =====
        
        // Payment Processing Chart
        if (document.getElementById('paymentProcessingChart')) {
            const processingData = <?php echo isset($analytics['payment_processing']) ? json_encode($analytics['payment_processing']) : '[]'; ?>;
            new Chart(document.getElementById('paymentProcessingChart'), {
                type: 'doughnut',
                data: {
                    labels: processingData.map(p => p.status),
                    datasets: [{
                        data: processingData.map(p => parseInt(p.count)),
                        backgroundColor: ['#f39c12', '#2ecc71', '#e74c3c']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 9 } } }
                    }
                }
            });
        }

        // ===== OPERATIONAL ANALYTICS CHARTS =====
        
        // Email Performance Chart
        if (document.getElementById('emailPerformanceChart')) {
            const emailData = <?php echo isset($analytics['email_performance']) ? json_encode($analytics['email_performance']) : '[]'; ?>;
            new Chart(document.getElementById('emailPerformanceChart'), {
                type: 'bar',
                data: {
                    labels: emailData.map(e => e.metric),
                    datasets: [{
                        label: 'Count',
                        data: emailData.map(e => parseInt(e.count)),
                        backgroundColor: ['#2ecc71', '#f39c12'],
                        borderRadius: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: { beginAtZero: true }
                    }
                }
            });
        }

        // Certificate Tracking Chart
        if (document.getElementById('certificateTrackingChart')) {
            const certData = <?php echo isset($analytics['certificate_tracking']) ? json_encode($analytics['certificate_tracking']) : '[]'; ?>;
            new Chart(document.getElementById('certificateTrackingChart'), {
                type: 'pie',
                data: {
                    labels: certData.map(c => c.metric),
                    datasets: [{
                        data: certData.map(c => parseInt(c.count)),
                        backgroundColor: ['#2ecc71', '#f39c12']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 9 } } }
                    }
                }
            });
        }
