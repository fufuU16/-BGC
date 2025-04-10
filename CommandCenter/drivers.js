
function searchDrivers() {
    const input = document.getElementById('searchInput').value.toLowerCase();
    const rows = document.querySelectorAll('#driversTable tbody tr');

    rows.forEach(row => {
        const name = row.cells[0].textContent.toLowerCase();
        const email = row.cells[1].textContent.toLowerCase();
        const rfidTag = row.cells[2].textContent.toLowerCase();

        if (name.includes(input) || email.includes(input) || rfidTag.includes(input)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}



function viewDriverDetails(driverId) {
    window.location.href = `driverDetails.php?driver_id=${encodeURIComponent(driverId)}`;
}
function addNewDriver() {
    window.location.href = 'addDriver.php';
}