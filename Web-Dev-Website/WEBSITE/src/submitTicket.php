<?php
session_start();
require_once 'databaseConfig.php';

function calculateTicketPrice($date) {
    $basePrice = 50; // Base price for a ticket
    $dateObj = new DateTime($date);
    $month = $dateObj->format('n');
    $day = $dateObj->format('j');

    // Pricing adjustments
    if ($month == 6) {
        $basePrice += 10; // June premium
    }

    if ($month == 12 && ($day >= 20 && $day <= 26)) {
        $basePrice += 15; // Christmas premium
    } elseif ($month >= 11 && $month <= 2) {
        $basePrice -= 5; // Winter discount
    }

    return $basePrice;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $departure = $_POST['departure'];
    $destination = $_POST['destination'];
    $date = $_POST['date'];
    $time = $_POST['time'];
    $seatType = $_POST['seatType'];
    $numSeats = $_POST['numSeats'];

    // Calculate ticket price
    $ticketPrice = calculateTicketPrice($date) * $numSeats; // Calculate total cost based on the date and number of seats

    // Prepare route query using named placeholders
    $routeQuery = "SELECT Route_ID FROM route WHERE routes = CONCAT(:departure, '-', :destination) OR routes = CONCAT(:destination, '-', :departure)";
    $stmt = $conn->prepare($routeQuery);
    $stmt->execute([':departure' => $departure, ':destination' => $destination]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        $routeId = $result['Route_ID'];

        // Prepare insert ticket query
        $insertTicketQuery = "INSERT INTO TICKET (Route_ID, Date, Time, Seat_Type, num_seats, Price) VALUES (?, ?, ?, ?, ?, ?)";
        $insertStmt = $conn->prepare($insertTicketQuery);
        $insertStmt->execute([$routeId, $date, $time, $seatType, $numSeats, $ticketPrice]);

        $lastInsertedTicketID = $conn->lastInsertId();
        $userID = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : NULL;

        // Prepare insert booking query
        $insertBookingQuery = "INSERT INTO Booking (User_ID, Ticket_ID) VALUES (?, ?)";
        $bookingStmt = $conn->prepare($insertBookingQuery);
        if ($bookingStmt->execute([$userID, $lastInsertedTicketID])) {
            $_SESSION['Booking_ID'] = $conn->lastInsertId();
            header("Location: payment.php"); // Redirect to payment.php
            exit();
        } else {
            echo "Error inserting booking.";
        }
    } else {
        echo "Route not found.";
    }
}
?>
