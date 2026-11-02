<?php

function catat_aktivitas(
    PDO $pdo,
    string $aksi,
    string $entitas,
    string $ringkasan,
    ?int $entitasId = null,
    array $metadata = [],
    ?array $aktor = null
): void {
    $aktor = $aktor ?? [
        'id' => $_SESSION['user_id'] ?? null,
        'nama' => $_SESSION['nama'] ?? 'Sistem',
        'role' => $_SESSION['role'] ?? 'sistem',
    ];

    $aktorId = isset($aktor['id']) ? (int)$aktor['id'] : null;
    $aktorNama = mb_substr((string)($aktor['nama'] ?? 'Sistem'), 0, 100, 'UTF-8');
    $aktorRole = mb_substr((string)($aktor['role'] ?? 'sistem'), 0, 20, 'UTF-8');
    $ip = isset($_SERVER['REMOTE_ADDR']) ? substr((string)$_SERVER['REMOTE_ADDR'], 0, 45) : null;
    $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? substr((string)$_SERVER['HTTP_USER_AGENT'], 0, 500) : null;
    $metadataJson = json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

    $stmt = $pdo->prepare(
        'INSERT INTO audit_logs
            (actor_id, actor_name, actor_role, action, entity, entity_id, summary, metadata, ip_address, user_agent)
         VALUES
            (:actor_id, :actor_name, :actor_role, :action, :entity, :entity_id, :summary, :metadata, :ip_address, :user_agent)'
    );
    $stmt->execute([
        ':actor_id' => $aktorId,
        ':actor_name' => $aktorNama,
        ':actor_role' => $aktorRole,
        ':action' => $aksi,
        ':entity' => $entitas,
        ':entity_id' => $entitasId,
        ':summary' => mb_substr($ringkasan, 0, 255, 'UTF-8'),
        ':metadata' => $metadataJson,
        ':ip_address' => $ip,
        ':user_agent' => $userAgent,
    ]);
}
