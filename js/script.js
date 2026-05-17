// =========================================================================
// Projet Réseau - Script principal (Interface et Animation)
// Responsable : Abasse ALI
// Rôle : Interface, ergonomie et intégration dynamique
// =========================================================================

// Gestion de l'affichage des barres d'outils
window.toggleToolbar = function(id) {
    document.querySelectorAll('.sub-toolbar').forEach(function(tb) {
        if (tb.id !== id) tb.classList.add('hidden');
    });
    var target = document.getElementById(id);
    if (target) target.classList.toggle('hidden');
};

document.addEventListener("DOMContentLoaded", function() {
    var container = document.getElementById('mynetwork');

    // Configuration du moteur Vis.js (physique désactivée pour mimiquer Packet Tracer)
    var options = {
        physics: { enabled: false },
        interaction: { dragNodes: true },
        nodes: {
            font: { size: 12, color: '#333', strokeWidth: 2, strokeColor: '#fff' },
            size: 30 // Taille des images
        },
        edges: {
            arrows: { to: { enabled: false } },
            color: '#2c3e50',
            font: { size: 10, align: 'middle' }
        }
    };

    // Chargement asynchrone de la topologie réseau
    fetch('application/logique.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=get_topology'
    })
    .then(response => response.text())
    .then(text => {
        try {
            var data = JSON.parse(text);
            
            // Restauration des coordonnées des équipements depuis le LocalStorage
            var savedPositions = JSON.parse(localStorage.getItem('topologyPositions')) || {};
            
            var positionsUpdated = false;
            
            data.nodes.forEach(function(node) {
                if (savedPositions[node.id]) {
                    node.x = savedPositions[node.id].x;
                    node.y = savedPositions[node.id].y;
                } else {
                    savedPositions[node.id] = { x: node.x, y: node.y };
                    positionsUpdated = true;
                }
            });
            
            if (positionsUpdated) {
                localStorage.setItem('topologyPositions', JSON.stringify(savedPositions));
            }

            var nodesDataSet = new vis.DataSet(data.nodes);
            var edgesDataSet = new vis.DataSet(data.edges);

            var network = new vis.Network(container, {
                nodes: nodesDataSet,
                edges: edgesDataSet
            }, options);

            // Algorithme anti-chevauchement des nœuds (Collisions basiques)
            function applyAntiOverlap() {
                var allPos = network.getPositions();
                var minDistance = 110;
                var changed = false;
                var nodeIds = Object.keys(allPos);
                
                for (var i = 0; i < nodeIds.length; i++) {
                    for (var j = i + 1; j < nodeIds.length; j++) {
                        var id1 = nodeIds[i];
                        var id2 = nodeIds[j];
                        var pos1 = allPos[id1];
                        var pos2 = allPos[id2];
                        
                        var dx = pos1.x - pos2.x;
                        var dy = pos1.y - pos2.y;
                        var distance = Math.sqrt(dx * dx + dy * dy);
                        
                        if (distance < minDistance) {
                            var angle = Math.atan2(dy, dx);
                            if (distance === 0) angle = Math.random() * Math.PI * 2;
                            
                            var pushDist = (minDistance - distance) / 2 + 5;
                            pos1.x += Math.cos(angle) * pushDist;
                            pos1.y += Math.sin(angle) * pushDist;
                            pos2.x -= Math.cos(angle) * pushDist;
                            pos2.y -= Math.sin(angle) * pushDist;
                            
                            network.moveNode(id1, pos1.x, pos1.y);
                            network.moveNode(id2, pos2.x, pos2.y);
                            changed = true;
                        }
                    }
                }
                return changed;
            }

            function savePositions() {
                var cleanPositions = {};
                var currentPos = network.getPositions();
                for (var k in currentPos) {
                    if (k === 'sim_packet') continue;
                    cleanPositions[k] = { x: currentPos[k].x, y: currentPos[k].y };
                }
                localStorage.setItem('topologyPositions', JSON.stringify(cleanPositions));
            }

            // Application itérative de la physique au chargement
            var iterations = 0;
            var needsSave = false;
            while (applyAntiOverlap() && iterations < 10) {
                iterations++;
                needsSave = true;
            }
            if (needsSave) savePositions();

            network.on("dragEnd", function (params) {
                applyAntiOverlap();
                savePositions();
            });

            // Requête asynchrone des métadonnées du nœud sélectionné
            network.on("click", function(params) {
                var detailsPanel = document.getElementById('node-details-panel');
                var contentDiv = document.getElementById('nd-content');
                var titleH3 = document.getElementById('nd-title');
                
                if (params.nodes && params.nodes.length > 0) {
                    var nodeId = params.nodes[0];
                    
                    fetch('application/logique.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: 'action=get_node_details&nodeId=' + encodeURIComponent(nodeId)
                    })
                    .then(response => response.json())
                    .then(data => {
                        if(data.success) {
                            detailsPanel.classList.remove('hidden');
                            
                            if (data.type === 'equipement') {
                                titleH3.textContent = "Détails : " + data.info.nom;
                                var html = "<p><strong>ID :</strong> " + data.info.id + " | <strong>Type :</strong> " + data.info.typeEq + "</p>";
                                
                                html += "<h4>Interfaces (Adresses)</h4>";
                                if (data.interfaces.length > 0) {
                                    html += "<table class='info-table'><tr><th>IP</th><th>MAC</th><th>Réseau</th><th>Action</th></tr>";
                                    data.interfaces.forEach(function(intf) {
                                        html += "<tr><td>"+intf.ip+"</td><td>"+intf.mac+"</td><td>"+intf.reseau+"</td>";
                                        html += "<td style='text-align:center;'><form action='application/logique.php' method='POST' style='margin:0; display:inline;'><input type='hidden' name='action' value='supprimer'><input type='hidden' name='typeElement' value='interface'><input type='hidden' name='idElement' value='"+intf.id+"'><button type='submit' class='btn-danger' style='padding:2px 5px; font-size:0.8em; width:auto; background-color:#e74c3c;' title='Supprimer interface'>❌</button></form></td></tr>";
                                    });
                                    html += "</table>";
                                } else {
                                    html += "<p><em>Aucune interface configurée.</em></p>";
                                }
                                
                                if (data.info.typeEq === 'Routeur' || (data.routes && data.routes.length > 0)) {
                                    html += "<h4>Table de Routage</h4>";
                                    if (data.routes && data.routes.length > 0) {
                                        html += "<table class='info-table'><tr><th>Destination</th><th>Passerelle</th><th>Action</th></tr>";
                                        data.routes.forEach(function(route) {
                                            html += "<tr><td>"+route.dest+"</td><td>"+route.nextHop+"</td>";
                                            html += "<td style='text-align:center;'><form action='application/logique.php' method='POST' style='margin:0; display:inline;'><input type='hidden' name='action' value='supprimer'><input type='hidden' name='typeElement' value='route'><input type='hidden' name='idElement' value='"+route.id+"'><button type='submit' class='btn-danger' style='padding:2px 5px; font-size:0.8em; width:auto; background-color:#e74c3c;' title='Supprimer route'>❌</button></form></td></tr>";
                                        });
                                        html += "</table>";
                                    } else {
                                        html += "<p><em>Aucune route statique définie.</em></p>";
                                    }
                                }
                                
                                html += "<hr style='border:0; border-top:1px solid #eee; margin:15px 0;'><form action='application/logique.php' method='POST' style='margin:0;' onsubmit='return confirm(\"Êtes-vous sûr de vouloir supprimer cet équipement ?\");'><input type='hidden' name='action' value='supprimer'><input type='hidden' name='typeElement' value='equipement'><input type='hidden' name='idElement' value='"+data.info.id+"'><button type='submit' class='btn-danger' style='width:100%;'>Supprimer l'équipement</button></form>";
                                contentDiv.innerHTML = html;
                                
                            } else if (data.type === 'reseau') {
                                titleH3.textContent = "Détails : Nuage (Réseau)";
                                var html = "<p><strong>ID :</strong> " + data.info.id + "<br><strong>Adresse :</strong> " + data.info.reseau + "</p>";
                                
                                html += "<h4>Hôtes connectés</h4>";
                                if (data.interfaces.length > 0) {
                                    html += "<table class='info-table'><tr><th>Équipement</th><th>IP</th><th>MAC</th><th>Action</th></tr>";
                                    data.interfaces.forEach(function(intf) {
                                        html += "<tr><td>"+intf.equipement+"</td><td>"+intf.ip+"</td><td>"+intf.mac+"</td>";
                                        html += "<td style='text-align:center;'><form action='application/logique.php' method='POST' style='margin:0; display:inline;'><input type='hidden' name='action' value='supprimer'><input type='hidden' name='typeElement' value='interface'><input type='hidden' name='idElement' value='"+intf.id+"'><button type='submit' class='btn-danger' style='padding:2px 5px; font-size:0.8em; width:auto; background-color:#e74c3c;' title='Supprimer interface'>❌</button></form></td></tr>";
                                    });
                                    html += "</table>";
                                } else {
                                    html += "<p><em>Aucun équipement connecté.</em></p>";
                                }
                                
                                html += "<hr style='border:0; border-top:1px solid #eee; margin:15px 0;'><form action='application/logique.php' method='POST' style='margin:0;' onsubmit='return confirm(\"Êtes-vous sûr de vouloir supprimer ce réseau ?\");'><input type='hidden' name='action' value='supprimer'><input type='hidden' name='typeElement' value='reseau'><input type='hidden' name='idElement' value='"+data.info.id+"'><button type='submit' class='btn-danger' style='width:100%;'>Supprimer le réseau</button></form>";
                                contentDiv.innerHTML = html;
                            }
                        }
                    })
                    .catch(err => console.error("Erreur de récupération AJAX :", err));
                }
            });

            var closeBtn = document.getElementById('nd-close');
            if (closeBtn) {
                closeBtn.addEventListener('click', function() {
                    document.getElementById('node-details-panel').classList.add('hidden');
                });
            }

            // =========================================================================
            // ANIMATION DE LA SIMULATION IP (TEMPS RÉEL)
            // =========================================================================
            if (typeof window.simulationData !== 'undefined' && window.simulationData.length > 0) {
                nodesDataSet.add({
                    id: 'sim_packet',
                    shape: 'image',
                    image: 'images/paquet.png',
                    size: 20,
                    hidden: true,
                    physics: false
                });

                let stepIndex = 0;
                let packetNodeId = 'sim_packet';

                function animateStep() {
                    if (stepIndex >= window.simulationData.length) {
                        setTimeout(() => nodesDataSet.update({id: packetNodeId, hidden: true}), 2000);
                        return;
                    }

                    let step = window.simulationData[stepIndex];
                    
                    if (step.erreur) {
                        // Remplacement visuel par une erreur SVG
                        var errorSvg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" width="60" height="60">' +
                                       '<rect x="8" y="16" width="48" height="32" fill="#f1c40f" stroke="#e67e22" stroke-width="4"/>' +
                                       '<polyline points="8,16 32,36 56,16" fill="none" stroke="#e67e22" stroke-width="4"/>' +
                                       '<line x1="16" y1="12" x2="48" y2="52" stroke="#e74c3c" stroke-width="8" stroke-linecap="round"/>' +
                                       '<line x1="48" y1="12" x2="16" y2="52" stroke="#e74c3c" stroke-width="8" stroke-linecap="round"/>' +
                                       '</svg>';
                        var url = "data:image/svg+xml;charset=utf-8," + encodeURIComponent(errorSvg);
                        nodesDataSet.update({id: packetNodeId, image: url, size: 30});

                        let liElements = document.querySelectorAll('.sim-step-item');
                        if (liElements[stepIndex]) {
                            liElements[stepIndex].style.display = 'block';
                            liElements.forEach(li => li.classList.remove('active-step'));
                            liElements[stepIndex].classList.add('active-step');
                            liElements[stepIndex].classList.add('erreur');
                            liElements[stepIndex].scrollIntoView({ behavior: 'smooth', block: 'end' });
                        }

                        setTimeout(() => {
                            if (stepIndex + 1 < window.simulationData.length) {
                                // Transformation du paquet en datagramme d'erreur ICMP pour le flux de retour
                                var icmpSvg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" width="60" height="60"><rect x="8" y="16" width="48" height="32" fill="#e74c3c" stroke="#c0392b" stroke-width="4"/><polyline points="8,16 32,36 56,16" fill="none" stroke="#c0392b" stroke-width="4"/></svg>';
                                var icmpUrl = "data:image/svg+xml;charset=utf-8," + encodeURIComponent(icmpSvg);
                                nodesDataSet.update({id: packetNodeId, image: icmpUrl, size: 20});
                                stepIndex++; animateStep();
                            } else {
                                nodesDataSet.update({id: packetNodeId, hidden: true});
                            }
                        }, 2500);
                        return;
                    }

                    let targetNodeId = step.node_id;
                    if (!targetNodeId || !network.getPositions([targetNodeId])[targetNodeId]) {
                        stepIndex++; animateStep(); return;
                    }

                    let targetPos = network.getPositions([targetNodeId])[targetNodeId];

                    // Scroll et mise en avant dans la console
                    let liElements = document.querySelectorAll('.sim-step-item');
                    if (liElements[stepIndex]) {
                        liElements[stepIndex].style.display = 'block';
                        liElements.forEach(li => li.classList.remove('active-step'));
                        liElements[stepIndex].classList.add('active-step');
                        liElements[stepIndex].scrollIntoView({ behavior: 'smooth', block: 'end' });
                    }

                    if (stepIndex === 0) {
                        nodesDataSet.update({id: packetNodeId, x: targetPos.x, y: targetPos.y, hidden: false});
                        setTimeout(() => { stepIndex++; animateStep(); }, 1200);
                    } else {
                        let currentPos = network.getPositions([packetNodeId])[packetNodeId];
                        
                        if (Math.abs(currentPos.x - targetPos.x) < 1 && Math.abs(currentPos.y - targetPos.y) < 1) {
                            // Effet visuel "Pulse" lors de processus locaux sur le même nœud (ex: ARP)
                            nodesDataSet.update({id: packetNodeId, size: 30});
                            setTimeout(() => { nodesDataSet.update({id: packetNodeId, size: 20}); stepIndex++; animateStep(); }, 800);
                        } else {
                            animateMove(currentPos, targetPos, 1000, () => { stepIndex++; animateStep(); }); // Déplacement
                        }
                    }
                }

                // Équation d'easing (Ease-In-Out) pour la translation des paquets
                function animateMove(from, to, duration, callback) {
                    let start = null;
                    function stepAnim(timestamp) {
                        if (!start) start = timestamp;
                        let progress = timestamp - start;
                        let pct = Math.min(progress / duration, 1);
                        let ease = pct < 0.5 ? 2 * pct * pct : 1 - Math.pow(-2 * pct + 2, 2) / 2; // Lissage de vitesse
                        nodesDataSet.update({id: packetNodeId, x: from.x + (to.x - from.x) * ease, y: from.y + (to.y - from.y) * ease});
                        if (progress < duration) window.requestAnimationFrame(stepAnim); else callback();
                    }
                    window.requestAnimationFrame(stepAnim);
                }

                setTimeout(animateStep, 1000);
            }

        } catch(e) {
            console.error("Erreur de format de données reçues du serveur !");
            console.error("Réponse brute de logique.php : ", text);
            container.innerHTML = "<p style='color:red; padding:20px;'>Erreur de chargement. Appuyez sur F12 et regardez l'onglet Console pour plus de détails.</p>";
        }
    });
});