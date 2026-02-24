<?php
// Election access token feature has been removed for voters.
// Redirect any direct access to the voting page.
header('Location: vote.php');
exit();
?>
