<?php

$hash = '$2y$12$GwOGlT81R4LStxev/OR.Z.0KgBU3HQpdnfFYi3Tc/skce2ATVFZM2';

$guesses = [
    'password',
    'admin',
    'admin123',
    'password123',
    // add your own likely passwords here
];

foreach ($guesses as $guess) {
    if (password_verify($guess, $hash)) {
        echo "MATCH: $guess\n";
        exit;
    }
}

echo "No match found.\n";
