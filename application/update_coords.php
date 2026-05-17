<?php
include_once("ConnectBDD.php");

if (isset($_POST['nodeId'], $_POST['x'], $_POST['y']) && isset($_SESSION['idUtilisateur'])) {
    $nodeId = $_POST['nodeId'];
    $x = (float)$_POST['x'];
    $y = (float)$_POST['y'];
    $idU = (int)$_SESSION['idUtilisateur'];
    
    if (strpos($nodeId, 'eq_') === 0) {
        $idEq = (int)str_replace('eq_', '', $nodeId);
        $stmt = $pdo->prepare("UPDATE equipement SET x = ?, y = ? WHERE idequipement = ? AND idutilisateur = ?");
        $stmt->execute([$x, $y, $idEq, $idU]);
    } elseif (strpos($nodeId, 'net_') === 0) {
        $idNet = (int)str_replace('net_', '', $nodeId);
        $stmt = $pdo->prepare("UPDATE reseau SET x = ?, y = ? WHERE idreseau = ? AND idutilisateur = ?");
        $stmt->execute([$x, $y, $idNet, $idU]);
    }
}
?>