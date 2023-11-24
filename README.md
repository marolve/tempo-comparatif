# TarifElec

Cette interface permet de comparer votre facture passée a celle que vous auriez eue avec un tarif de base, heures creuses, EDF Tempo ou Zen Week-End.

Vos données de consommation horaire peuvent être récupérées sur https://www.enedis.fr/particulier dans la rubrique "Suivre mes mesures" puis cliquer sur "Télécharger mes données"
Il faut choisir l'option "Consommation horaire". Enedis ne conserve que 2 ans d'historique.

C'est une variante du très bon travail de Guillaume Frémont (disponible ici : https://tarifelec.vraiment.top et le code : https://github.com/grimmlink/tempo-comparatif )

Nouveautés de cette version :
- possibilité de modifier tous les paramètres de calcul (sauf les dates des couleurs des jours Tempo, disponible dans le fichier tempo.json).
- ajout d'un calcul Tempo "corrigé" qui lorsqu'on a suffisamment de données, permet de rétablir la même proportion de jours rouges et blancs tels que spécifiés dans le contrat (22 jours rouges et 33 jours blancs)
- ajout de l'option Zen Week-End
- ajout des détails de calcul pour les différentes options
- plus besoin de supprimer les entêtes du fichier horaires Enedis

Une instance est disponible ici : https://electricite-calcul.onrender.com/ (patientez quelque secondes pour le redémarrage du serveur).
