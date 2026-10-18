<?php
//System passwords for reference
$passwords = [
    'superadmin' => 'superadmin123',
    'Dr. Collins' => 'collins',
    'drjohn' => 'Doctor123',
    'nursejane' => 'Nurse@456',
    'pharma1' => 'Pharma789',
];

echo "<h2>Password Hashes</h2>";
echo "<table border='1' cellpadding='10'>";
echo "<tr><th>Username</th><th>Plain Password</th><th>Hashed Password</th></tr>";

foreach ($passwords as $username => $password) {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    echo "<tr>";
    echo "<td>$username</td>";
    echo "<td>$password</td>";
    echo "<td>$hash</td>";
    echo "</tr>";
}

echo "</table>";
?>