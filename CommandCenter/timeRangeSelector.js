// timeRangeSelector.js

document.addEventListener('DOMContentLoaded', function () {
    const timeRangeSelector = document.getElementById('timeRangeSelector');
    const exportButton = document.getElementById('exportButton');
    const historyTableBody = document.getElementById('historyTableBody');

    async function fetchHistoryData(timeRange) {
        try {
            const response = await fetch(`fetch_passenger.php?range=${timeRange}`);
            const historyData = await response.json();

            // Clear existing body
            historyTableBody.innerHTML = '';

            if (historyData.length > 0) {
                const dataByDate = {};

                // Organize data by date
                historyData.forEach(item => {
                    if (!dataByDate[item.date]) {
                        dataByDate[item.date] = {
                            "ARCA South Route": 0,
                            "Central Route": 0,
                            "East Route": 0,
                            "North Route": 0,
                            "Weekend Route": 0,
                            "West Route": 0,
                            "Overall": 0
                        };
                    }
                    dataByDate[item.date][item.route] = item.total_passengers;
                    dataByDate[item.date]["Overall"] = Number(dataByDate[item.date]["Overall"]) + Number(item.total_passengers);

                    
                });

                // Populate table body
                Object.entries(dataByDate).forEach(([date, routeData]) => {
                    const row = document.createElement('tr');
                    row.innerHTML = `<td>${date}</td>`;

                    ["ARCA South Route", "Central Route", "East Route", "North Route", "Weekend Route", "West Route", "Overall"].forEach(route => {
                        const count = routeData[route] || 0;
                        row.innerHTML += `<td>${count}</td>`;
                    });

                    historyTableBody.appendChild(row);
                });
            } else {
                historyTableBody.innerHTML = '<tr><td colspan="8">No data available.</td></tr>';
            }
        } catch (error) {
            console.error("Error fetching history data:", error);
        }
    }

    timeRangeSelector.addEventListener('change', function () {
        fetchHistoryData(this.value);
    });

    exportButton.addEventListener('click', function () {
        exportHistoryDataToPDF();
    });

    // Initial fetch for the default time range
    fetchHistoryData(timeRangeSelector.value);
});