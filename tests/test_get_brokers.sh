#!/bin/bash
# test_get_brokers.sh
API_TOKEN="ba237b37fb7db63e55f59e2fa10eac752007c63fe55301b529acf06ae62bb8b3"
API_URL="https://localhost/cashcue/api/getBrokerAccounts.php"

echo
echo "==============================="
echo "Testing GET with api_token parameter"
echo "==============================="
echo "Command:"
echo "curl -k \"${API_URL}?api_token=${API_TOKEN}\""
echo
# Exécution et affichage JSON formaté
curl -k -s "${API_URL}?api_token=${API_TOKEN}" | jq
echo

echo "==============================="
echo "Testing GET with Authorization header"
echo "==============================="
echo "Command:"
echo "curl -k -H \"Authorization: Bearer ${API_TOKEN}\" \"${API_URL}\""
echo
# Exécution et affichage JSON formaté
curl -k -s -H "Authorization: Bearer ${API_TOKEN}" "${API_URL}" | jq
echo