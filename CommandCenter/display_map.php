@import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;700&display=swap');

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'Poppins', sans-serif;
}

body {
    background-image: url('../image/bgbg.jpg');
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    background-attachment: fixed;
    display: flex;
    flex-direction: column;
    position: relative;
}

body::before {
    content: '';
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    z-index: -1;
    pointer-events: none;
}

header {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    padding: 10px 20px;
    width: 100%;
    height: 80px;
    position: relative;
    z-index: 1;
}

.logo img {
    width: 40px;
    margin-left: 20px;
}

nav {
    margin-right: 20px;
    display: flex;
    align-items: center;
}

nav a, .dropbtn {
    color: #ffffff;
    margin: 0 30px;
    text-decoration: none;
    font-weight: bold;
    position: relative;
    transition: background-color 0.3s ease;
}

.dropdown {
    position: relative;
    display: inline-block;
}

.dropdown-content {
    display: none;
    position: absolute;
    background-color: rgba(51, 51, 51, 0.211);
    backdrop-filter: blur(10px);
    min-width: 160px;
    box-shadow: 0 8px 16px rgba(0, 0, 0, 0.059);
    z-index: 1;
    border-radius: 8px;
    overflow: hidden;
}

.dropdown-content a {
    color: #ffffff;
    padding: 12px 16px;
    text-decoration: none;
    display: block;
    font-weight: normal;
    transition: background-color 0.3s ease;
}

.dropdown-content a:hover {
    background-color: #575757;
}

.dropdown:hover .dropdown-content {
    display: block;
}

.dropdown:hover .dropbtn {
    background-color: #575757;
    border-radius: 8px;
}

nav a.active {
    font-weight: bold;
    color: #ffffff;
    box-shadow: 0 4px 2px -2px gray;
    background-color: #575757;
    border-radius: 8px;
    padding: 10px;
}

.dropbtn.active-dropdown {
    box-shadow: 0 4px 2px -2px gray;
    background-color: #575757;
    border-radius: 8px;
    padding: 10px;
}

.Title {
    margin-top: 50px;
    display: flex;
    justify-content: center;
}

.Title h1 {
    font-size: 36px;
    font-weight: bold;
    color: #ffffff;
}

#PassengerDetails, #PassengerHistory, #BusStopDetails, #MapContainer {
    width: 60%;
    max-width: 1000px;
    margin: 20px auto;
    padding: 10px;
    background-color: #FFFFFF;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    overflow: auto;
}

#PassengerDetails, #BusStopDetails {
    height: 600px;
    overflow-y: auto;
}

#PassengerHistory {
    height: auto;
    overflow-x: auto;
}

#MapContainer {
    height: 500px;
}

#PassengerDetails h2, #PassengerHistory h2, #BusStopDetails h2, #MapContainer h2 {
    font-size: 24px;
    color: #2A9D8F;
    margin-bottom: 10px;
    text-align: center;
    border-bottom: 2px solid #2A9D8F;
    padding-bottom: 5px;
}

table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 10px;
    display: block;
    overflow-x: auto;
}

table th, table td {
    border: 1px solid #ddd;
    padding: 8px;
    text-align: center;
    font-size: 14px;
}

table th {
    background-color: #2A9D8F;
    color: white;
}

table tr:hover {
    background-color: #f1f1f1;
}

form {
    display: flex;
    justify-content: center;
    margin-bottom: 10px;
}

input[type="text"] {
    width: 250px;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 5px;
    margin-right: 10px;
    font-size: 14px;
}

button[type="submit"] {
    padding: 8px 16px;
    background-color: #2A9D8F;
    color: white;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-size: 14px;
    transition: background-color 0.3s;
}

button[type="submit"]:hover {
    background-color: #21867a;
}

.username-display {
    position: absolute;
    left: 20px;
    font-weight: bold;
    color: #ffffff;
}

/* Responsive styles */
@media (max-width: 768px) {
    #PassengerDetails, #PassengerHistory, #BusStopDetails, #MapContainer {
        width: 95%;
    }

    .Title h1 {
        font-size: 28px;
    }
}

@media (max-width: 480px) {
    .Title h1 {
        font-size: 24px;
    }
}