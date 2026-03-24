# Add-on Wiegand pour Home Assistant

Ce module permet de gérer un clavier/lecteur de badge RFID Wiegand branché sur un ESP (ESPHome) via une interface web sécurisée, directement intégrée dans Home Assistant.

## 🚀 Fonctionnalités
- Panneau d'administration intégré à la barre latérale de HA (Ingress).
- Création de codes PIN / Badges à usage unique ou permanent.
- Déclenchement de Webhooks Home Assistant.
- Cryptage AES-256 des données locales.

## 📦 Installation

1. Dans Home Assistant, allez dans **Paramètres** > **Modules complémentaires** > **Boutique**.
2. Cliquez sur les 3 petits points en haut à droite > **Dépôts**.
3. Ajoutez l'URL de ce dépôt GitHub : `https://github.com/Zukkerr/ha-pincode`
4. Cliquez sur Ajouter, puis fermez la fenêtre.
5. Rafraîchissez la page (ou cliquez sur "Recharger").
6. Cherchez "Accès Wiegand" en bas de la page et installez-le !

## ⚙️ Configuration du NodeMCU (ESPHome)
Voici la requête HTTP à utiliser dans votre code ESPHome :
`url: "http://homeassistant:8080/trigger.php"`
