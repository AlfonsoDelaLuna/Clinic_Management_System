<?php
echo "Hash for admin1234: " . password_hash("admin1234", PASSWORD_DEFAULT) . "<br>";
echo "Hash for guest1234: " . password_hash("guest1234", PASSWORD_DEFAULT) . "<br>";
echo "Hash for STIadmin1234: " . password_hash("STIadmin1234", PASSWORD_DEFAULT);
?>