<?php
base_page_header('',"Allowed Entities","Allowed Entities");

$db = connectToDB();

// get all "allowed_to_register" entities
$sql = "SELECT alliance_id, alliance_name, alliance_ticker FROM alliances WHERE is_allowed_to_reg = 1";
$res = $db->query($sql);

if ($res->num_rows > 0)
{
    echo "<h3>Alliances (Full Member)</h3>";

    echo "The following alliances and all their corporations are allowed to register for this services as <b>Full Members</b>:<br />";

    echo "<table style=\"width: 95%\"><tr>
        <th class=\"your_characters_header\">Alliance ID / Ticker</th>
        <th class=\"your_characters_header\">Alliance Name</th>
        <th class=\"your_characters_header\">Actions</th></tr>";



    while ($row = $res->fetch_array())
    {
        $all_id = $row['alliance_id'];
        echo "<tr>";
        echo "<td>" . $all_id . " / " . $row['alliance_ticker'] . "</td><td>" .  $row['alliance_name'] . "</td>";
        echo "<td>Remove</td>";
        echo "</tr>";

        // get all corporations of this alliance
        $sql = "SELECT corp_id, corp_name, corp_ticker, is_allowed_to_reg, is_allied FROM corporations WHERE alliance_id = $all_id";
        $res2 = $db->query($sql);

        while ($row2 = $res2->fetch_array())
        {
            echo "<tr><td>&nbsp;</td><td colspan=\"2\">" . $row2['corp_id'] . " / " . $row2['corp_ticker'] . " / " . $row2['corp_name'] . "</td></tr>";
        }

    }

    echo "</table>";
}

// get all "allowed_to_register" corporations
$sql = "SELECT corp_id, corp_name, corp_ticker FROM corporations WHERE is_allowed_to_reg = 1";
$res = $db->query($sql);

if ($res->num_rows > 0)
{
    echo "<h3>Corporations (Full Member)</h3>";

    echo "The following corporations are explictly allowed to register for this services as <b>Full Members</b>:<br />";

    echo "<table style=\"width: 95%\"><tr>
        <th class=\"your_characters_header\">Corp ID / Ticker</th>
        <th class=\"your_characters_header\">Corp Name</th>
        <th class=\"your_characters_header\">Actions</th></tr>";



    while ($row = $res->fetch_array())
    {
        echo "<tr>";
        echo "<td>" . $row['corp_id'] . " / " . $row['corp_ticker'] . "</td><td>" .  $row['corp_name'] . "</td>";
        echo "<td>Remove</td>";
        echo "</tr>";

    }

    echo "</table>";
}





// get all "is_allied" entities
$sql = "SELECT alliance_id, alliance_name, alliance_ticker FROM alliances WHERE is_allied = 1";
$res = $db->query($sql);

if ($res->num_rows > 0) {
    echo "<h3>Alliances (Allied)</h3>";

    echo "The following alliances and all their corporations are allowed to register for this services as <b>Allied Members</b>:<br />";


    echo "<table style=\"width: 95%\"><tr>
        <th class=\"your_characters_header\">Alliance ID / Ticker</th>
        <th class=\"your_characters_header\">Alliance Name</th>
        <th class=\"your_characters_header\">Actions</th></tr>";



    while ($row = $res->fetch_array())
    {
        $all_id = $row['alliance_id'];
        echo "<tr>";
        echo "<td>" . $all_id . " / " . $row['alliance_ticker'] . "</td><td>" .  $row['alliance_name'] . "</td>";
        echo "<td>Remove</td>";
        echo "</tr>";

        // get all corporations of this alliance
        $sql = "SELECT corp_id, corp_name, corp_ticker, is_allowed_to_reg, is_allied FROM corporations WHERE alliance_id = $all_id";
        $res2 = $db->query($sql);

        while ($row2 = $res2->fetch_array())
        {
            echo "<tr><td>&nbsp;</td><td colspan=\"2\">" . $row2['corp_id'] . " / " . $row2['corp_ticker'] . " / " . $row2['corp_name'] . "</td></tr>";
        }

    }

    echo "</table>";
}



// get all "allied" corporations
$sql = "SELECT corp_id, corp_name, corp_ticker FROM corporations WHERE is_allied = 1";
$res = $db->query($sql);

if ($res->num_rows > 0)
{
    echo "<h3>Corporations (Allied Member)</h3>";

    echo "The following corporations are explictly allowed to register for this services as <b>Allied Members</b>:<br />";

    echo "<table style=\"width: 95%\"><tr>
        <th class=\"your_characters_header\">Corp ID / Ticker</th>
        <th class=\"your_characters_header\">Corp Name</th>
        <th class=\"your_characters_header\">Actions</th></tr>";



    while ($row = $res->fetch_array())
    {
        echo "<tr>";
        echo "<td>" . $row['corp_id'] . " / " . $row['corp_ticker'] . "</td><td>" .  $row['corp_name'] . "</td>";
        echo "<td>Remove</td>";
        echo "</tr>";

    }

    echo "</table>";
}



base_page_footer('1','');
?>