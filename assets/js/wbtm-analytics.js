jQuery(document).ready(function($) {
    // Check if wbtmAnalytics object exists
    if (typeof wbtmAnalytics === 'undefined') {
        console.error('Analytics data not found');
        return;
    }

    // Routes Chart
    if(document.getElementById('routesChart')) {
        new Chart(document.getElementById('routesChart'), {
            type: 'pie',
            data: {
                labels: Object.keys(wbtmAnalytics.popularRoutes),
                datasets: [{
                    data: Object.values(wbtmAnalytics.popularRoutes),
                    backgroundColor: [
                        '#4e73df',
                        '#1cc88a',
                        '#36b9cc',
                        '#f6c23e',
                        '#e74a3b'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20
                        }
                    }
                }
            }
        });
    }

    // Revenue Chart
    if(document.getElementById('revenueChart')) {
        new Chart(document.getElementById('revenueChart'), {
            type: 'line',
            data: {
                labels: wbtmAnalytics.months,
                datasets: [{
                    label: 'Revenue',
                    data: wbtmAnalytics.revenue,
                    borderColor: '#4e73df',
                    backgroundColor: 'rgba(78, 115, 223, 0.1)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '$' + value;
                            }
                        }
                    }
                }
            }
        });
    }

    // Peak Hours Chart
    if(document.getElementById('peakHoursChart')) {
        new Chart(document.getElementById('peakHoursChart'), {
            type: 'bar',
            data: {
                labels: Object.keys(wbtmAdvancedAnalytics.peakHours),
                datasets: [{
                    label: 'Bookings',
                    data: Object.values(wbtmAdvancedAnalytics.peakHours),
                    backgroundColor: 'rgba(78, 115, 223, 0.8)'
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }

    // Weekly Pattern Chart
    if(document.getElementById('weeklyPatternChart')) {
        const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        new Chart(document.getElementById('weeklyPatternChart'), {
            type: 'line',
            data: {
                labels: days,
                datasets: [{
                    label: 'Bookings',
                    data: days.map((_, i) => wbtmAdvancedAnalytics.weeklyStats[i] || 0),
                    borderColor: '#1cc88a',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true
            }
        });
    }

    // Ticket Types Chart
    if(document.getElementById('ticketTypesChart')) {
        new Chart(document.getElementById('ticketTypesChart'), {
            type: 'doughnut',
            data: {
                labels: Object.keys(wbtmAdvancedAnalytics.ticketTypes),
                datasets: [{
                    data: Object.values(wbtmAdvancedAnalytics.ticketTypes),
                    backgroundColor: [
                        '#4e73df',
                        '#1cc88a',
                        '#36b9cc',
                        '#f6c23e'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }

    // Bus Type Performance Chart
    if(document.getElementById('busTypeChart')) {
        const busTypes = Object.keys(wbtmAdvancedAnalytics.busTypes);
        new Chart(document.getElementById('busTypeChart'), {
            type: 'bar',
            data: {
                labels: busTypes,
                datasets: [{
                    label: 'Revenue',
                    data: busTypes.map(type => wbtmAdvancedAnalytics.busTypes[type].revenue),
                    backgroundColor: 'rgba(78, 115, 223, 0.8)'
                }, {
                    label: 'Bookings',
                    data: busTypes.map(type => wbtmAdvancedAnalytics.busTypes[type].bookings),
                    backgroundColor: 'rgba(28, 200, 138, 0.8)'
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }
});
