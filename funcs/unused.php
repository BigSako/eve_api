<?php
die();

// unused functions to plot stuff with jpgraph
function printTimePlot($title, $x, $y) {
	require_once ('jpgraph/jpgraph.php');
	require_once ('jpgraph/jpgraph_line.php');
	require_once ('jpgraph/jpgraph_date.php');
	$graph = new Graph(550,350);
	$graph->SetScale("datlin");
	$theme_class=new UniversalTheme;
	$graph->SetTheme($theme_class);
	$p1 = new LinePlot($y, $x);
	$graph->Add($p1);
	$graph->title->Set($title);
	$graph->xaxis->SetLabelAngle(90);
	$graph->xaxis->scale->SetDateFormat( 'Y-m-d' );
	$p1->SetColor("#1111cc");
	$p1->SetFillColor('lightblue@0.5');
	$graph->SetMargin(40,20,30,90);
	$tmpFile = "/tmp/tmp_graphgraph.png";
	if (file_exists($tmpFile)) { unlink($tmpFile); }
	$graph->Stroke($tmpFile);
	echo "<img src=\"data: png;base64," . base64_encode(file_get_contents($tmpFile)) . "\">";
}

function printXYPlot($title, $x, $y) {
	require_once ('jpgraph/jpgraph.php');
	require_once ('jpgraph/jpgraph_line.php');
	require_once ('jpgraph/jpgraph_date.php');
	$graph = new Graph(550,350);
	$graph->SetScale("textlin");
	$theme_class=new UniversalTheme;
	$graph->SetTheme($theme_class);
	$p1 = new LinePlot($y);
	$graph->Add($p1);
	$graph->xaxis->SetTickLabels($x);
	$graph->title->Set($title);
	$p1->SetColor("#1111cc");
	$p1->SetFillColor('lightblue@0.5');
	$graph->SetMargin(40,20,30,50);
	$tmpFile = "/tmp/tmp_graphgraph.png";
	if (file_exists($tmpFile)) { unlink($tmpFile); }
	$graph->Stroke($tmpFile);
	echo "<img src=\"data: png;base64," . base64_encode(file_get_contents($tmpFile)) . "\">";
}

function printPie($title, $data, $legend, $color = array()) {
	require_once ('jpgraph/jpgraph.php');
	require_once ('jpgraph/jpgraph_pie.php');
	require_once ('jpgraph/jpgraph_pie3d.php');
	$graph = new PieGraph(550,350);
	$theme_class= new VividTheme;
	$graph->SetTheme($theme_class);
	$graph->title->Set($title);
	$p1 = new PiePlot3D($data);
	$graph->Add($p1);
	$p1->SetCenter(0.43, 0.5);
	$p1->SetLabelType(PIE_VALUE_ABS);
	$p1->value->SetFormat("%d");
	$p1->value->Show();
	$p1->ShowBorder();
	$p1->SetColor('black');
	if (count($color) == count($data)) {
		$p1->SetSliceColors($color);
	}
	
	$p1->setLegends($legend);
	$graph->legend->SetPos(0.0, 0.05);
	$graph->legend->SetColumns(1);
	$tmpFile = "/tmp/tmp_graphgraph.png";
	if (file_exists($tmpFile)) { unlink($tmpFile); }
	$graph->Stroke($tmpFile);
	echo "<img src=\"data: png;base64," . base64_encode(file_get_contents($tmpFile)) . "\">";
}

function printMultiGraph($title, $data1, $data2, $legend) {
	require_once ('jpgraph/jpgraph.php');
	require_once ('jpgraph/jpgraph_bar.php');
	$graph = new Graph(550,350,'auto');
	$graph->SetScale("textlin");

	$theme_class=new UniversalTheme;
	$graph->SetTheme($theme_class);

	$graph->yaxis->SetTickPositions(array(0,30,60,90,120,150), array(15,45,75,105,135));
	$graph->SetBox(false);

	$graph->ygrid->SetFill(false);
	$graph->xaxis->SetTickLabels($legend);
	$graph->yaxis->HideLine(false);
	$graph->yaxis->HideTicks(false,false);

	// Create the bar plots
	$b1plot = new BarPlot($data1);
	$b2plot = new BarPlot($data2);

	// Create the grouped bar plot
	$gbplot = new GroupBarPlot(array($b1plot,$b2plot));
	// ...and add it to the graPH
	$graph->Add($gbplot);

	$b1plot->SetColor("white");
	$b1plot->SetFillColor("#cc1111");
	$b1plot->SetLegend("3 Months");
	
	$b2plot->SetColor("white");
	$b2plot->SetFillColor("#1111cc");
	$b2plot->SetLegend("1 Month");
	
	$graph->title->Set($title);
	$tmpFile = "/tmp/tmp_graphgraph.png";
	if (file_exists($tmpFile)) { unlink($tmpFile); }
	$graph->Stroke($tmpFile);
	echo "<img src=\"data: png;base64," . base64_encode(file_get_contents($tmpFile)) . "\">";
}
?>