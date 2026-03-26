<?php

$projectRequirements = new \RequirementsChecker\ProjectRequirements(getcwd());

$failedRequirements = $projectRequirements->getFailedRequirements();
$failedRecommendations = $projectRequirements->getFailedRecommendations();


if(!count($failedRequirements) && !count($failedRecommendations)){
    return;
}

echo "<h1>Project Requirements Checker</h1> <br><br>";

if(count($failedRequirements)){
    echo "<h2>Failed Requirements:</h2> <br><br>";
    foreach ($failedRequirements as $index => $failedRequirement){

        $index++;
        echo "<h4>{$index}. {$failedRequirement->getTestMessage()}</h4>";
        echo $failedRequirement->getHelpHtml() . "<br>";
        echo "===========================================" ."<br>";
    }
}

if(count($failedRecommendations)){
    echo "<h2>Failed Recommendations:</h2> <br><br>";
    foreach ($failedRecommendations as $index => $failedRequirement){

        $index++;
        echo "<h4>{$index}. {$failedRequirement->getTestMessage()}</h4>";
        echo $failedRequirement->getHelpHtml() . "<br>";
        echo "===========================================" ."<br>";
    }
}

exit;
