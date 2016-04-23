<?php

$db = connectToDB();


if (isset($_REQUEST['timerId']))
{
    $timerId = intval($_REQUEST['timerId']);

    if (isset($_REQUEST['do']))
    {
        $do = $_REQUEST['do'];

        if ($do == "delete2")
        {
            $sql = "DELETE FROM timerboard WHERE timerID = $timerId";
            $db->query($sql);
            header('Location: api.php?action=timerboard');
            exit();

        }
    }

} else {
    echo "Error: no timerId set";
}

?>