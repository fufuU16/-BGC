document.addEventListener('DOMContentLoaded', function () {
    fetchLogs();
});

function fetchLogs() {
    // Fetch logs from the server using Fetch API
    fetch('fetch_logs.php') // Replace with your server endpoint
        .then(response => response.json())
        .then(logs => {
            const tableBody = document.querySelector('#logsTable tbody');
            tableBody.innerHTML = ''; // Clear existing rows

            logs.forEach(log => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${log.driver}</td>
                    <td>${log.bus}</td>
                    <td>${log.date}</td>
                    <td>${log.time}</td>
                    <td>${log.status}</td>
                    <td>${log.moreInfo}</td>
                `;
                tableBody.appendChild(row);
            });
        })
        .catch(error => console.error('Error fetching logs:', error));
}

function searchLogs() {
    const searchInput = document.getElementById('searchInput').value.toLowerCase();
    const tableRows = document.querySelectorAll('#logsTable tbody tr');

    tableRows.forEach(row => {
        const cells = row.querySelectorAll('td');
        const match = Array.from(cells).some(cell => cell.textContent.toLowerCase().includes(searchInput));
        row.style.display = match ? '' : 'none';
    });
}


function exportReport() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();

    // Define a template for the PDF
    const pageWidth = doc.internal.pageSize.getWidth();
    const pageHeight = doc.internal.pageSize.getHeight();

    // Add a header
    doc.setFontSize(18);
    doc.text("Bus Schedule Report", pageWidth / 2, 20, { align: "center" });

    // Add timestamp and username
    const username = "<?php echo htmlspecialchars($_SESSION['username']); ?>";
    const timestamp = new Date().toLocaleString();
    doc.setFontSize(12);
    doc.text(`Exported by: ${username}`, 14, 30);
    doc.text(`Timestamp: ${timestamp}`, 14, 36);

    // Add a footer
    doc.setFontSize(10);
    doc.text("Confidential", pageWidth / 2, pageHeight - 10, { align: "center" });

    // Add table headers
    let headers = [];
    let table = document.getElementById("logsTable");
    let rows = table.getElementsByTagName("tr");
    let headerCells = rows[0].getElementsByTagName("th");
    for (let headerCell of headerCells) {
        headers.push(headerCell.innerText);
    }

    // Add table data
    let data = [];
    for (let i = 1; i < rows.length; i++) {
        let cells = rows[i].getElementsByTagName("td");
        if (cells.length > 0) {
            let rowData = [];
            for (let cell of cells) {
                rowData.push(cell.innerText);
            }
            data.push(rowData);
        }
    }

    // Add table to PDF
    doc.autoTable({
        startY: 40,
        head: [headers],
        body: data,
        theme: 'grid',
        styles: { fontSize: 10 },
        headStyles: { fillColor: [100, 100, 100] },
        alternateRowStyles: { fillColor: [240, 240, 240] },
    });

    // Add a watermark image with reduced opacity
    const imgData = "../image/bgcwatermark.PNG"; // Replace with actual Base64 encoded image data
    const imgWidth = pageWidth * 0.8; // Adjust the size as needed
    const imgHeight = pageHeight * 0.8; // Adjust the size as needed
    const imgX = (pageWidth - imgWidth) / 2;
    const imgY = (pageHeight - imgHeight) / 2;
    doc.addImage(imgData, 'PNG', imgX, imgY, imgWidth, imgHeight, '', 'FAST');

    // Save the PDF
    doc.save("Bus_Schedule_Report.pdf");
}
function viewDriverList() {
    window.location.href = 'drivers.php';
}