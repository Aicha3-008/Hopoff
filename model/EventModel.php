<?php
class EventModel {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function getEvents($email, $role) {
        $currentYear = date('Y');
        $stmt = $this->db->prepare("
            SELECT id, title, start_date, end_date, color, fixed, is_global
            FROM events
            WHERE user_email = ? OR fixed = 1 OR is_global = 1
        ");
        $stmt->execute([$email]);
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(function($event) use ($currentYear, $role) {
            $event['editable'] = $event['fixed'] == 0 && ($event['is_global'] == 0 || $role === 'rh');
            if ($event['fixed'] == 1) {
                $startDate = new DateTime($event['start_date']);
                $startDate->setDate($currentYear, $startDate->format('m'), $startDate->format('d'));
                $event['start'] = $startDate->format('Y-m-d H:i:s');

                if ($event['end_date']) {
                    $endDate = new DateTime($event['end_date']);
                    $endDate->setDate($currentYear, $endDate->format('m'), $endDate->format('d'));
                    $event['end'] = $endDate->format('Y-m-d H:i:s');
                } else {
                    $event['end'] = null;
                }
            } else {
                $event['start'] = $event['start_date'];
                $event['end'] = $event['end_date'];
            }
            unset($event['start_date'], $event['end_date'], $event['fixed']);
            return $event;
        }, $events);
    }

    public function isFixedEvent($id) {
        $stmt = $this->db->prepare("SELECT fixed FROM events WHERE id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result && $result['fixed'] == 1;
    }

    public function isGlobalEvent($id) {
        $stmt = $this->db->prepare("SELECT is_global FROM events WHERE id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result && $result['is_global'] == 1;
    }

    public function addEvent($email, $title, $startDate, $endDate, $color, $isGlobal = 0) {
        $stmt = $this->db->prepare("
            INSERT INTO events (user_email, title, start_date, end_date, color, fixed, is_global)
            VALUES (?, ?, ?, ?, ?, 0, ?)
        ");
        return $stmt->execute([$email, $title, $startDate, $endDate, $color, $isGlobal]);
    }

    public function updateEvent($id, $email, $title, $startDate, $endDate, $color, $role) {
        if ($this->isFixedEvent($id)) {
            return false;
        }
        if ($this->isGlobalEvent($id) && $role !== 'rh') {
            return false;
        }
        $stmt = $this->db->prepare("
            UPDATE events
            SET title = ?, start_date = ?, end_date = ?, color = ?
            WHERE id = ? AND (user_email = ? OR is_global = 1) AND fixed = 0
        ");
        return $stmt->execute([$title, $startDate, $endDate, $color, $id, $email]);
    }

    public function deleteEvent($id, $email, $role) {
        if ($this->isFixedEvent($id)) {
            return false;
        }
        if ($this->isGlobalEvent($id) && $role !== 'rh') {
            return false;
        }
        $stmt = $this->db->prepare("
            DELETE FROM events
            WHERE id = ? AND (user_email = ? OR is_global = 1) AND fixed = 0
        ");
        return $stmt->execute([$id, $email]);
    }

    public function getUpcomingEvents($email, $limit, $role) {
        $currentYear = date('Y');
        $today = date('Y-m-d');
        $stmt = $this->db->prepare("
            SELECT id, title, start_date, end_date, color, fixed, is_global,
                   CASE
                       WHEN fixed = 1 THEN 
                           STR_TO_DATE(
                               CONCAT(
                                   IF(DATE_FORMAT(start_date, '%m-%d') >= DATE_FORMAT(CURDATE(), '%m-%d'),
                                      :currentYear, :currentYear + 1),
                                   '-', DATE_FORMAT(start_date, '%m-%d')
                               ),
                               '%Y-%m-%d'
                           )
                       ELSE start_date
                   END AS effective_start
            FROM events
            WHERE user_email = :email OR fixed = 1 OR is_global = 1
            ORDER BY effective_start ASC
            LIMIT :limit
        ");
        $stmt->bindValue(':email', $email, PDO::PARAM_STR);
        $stmt->bindValue(':currentYear', $currentYear, PDO::PARAM_INT);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("Fetched upcoming events for $email: " . print_r($events, true));

        return array_map(function($event) use ($currentYear, $role) {
            $event['editable'] = $event['fixed'] == 0 && ($event['is_global'] == 0 || $role === 'rh');
            if ($event['fixed'] == 1) {
                $startDate = new DateTime($event['start_date']);
                $startMonthDay = $startDate->format('m-d');
                $effectiveYear = (date('m-d') <= $startMonthDay) ? $currentYear : ($currentYear + 1);
                $startDate->setDate($effectiveYear, $startDate->format('m'), $startDate->format('d'));
                $event['start_date'] = $startDate->format('Y-m-d H:i:s');

                if ($event['end_date']) {
                    $endDate = new DateTime($event['end_date']);
                    $endDate->setDate($effectiveYear, $endDate->format('m'), $endDate->format('d'));
                    $event['end_date'] = $endDate->format('Y-m-d H:i:s');
                }
            }
            $event['start'] = $event['start_date'];
            $event['end'] = $event['end_date'] ?: null;
            unset($event['fixed'], $event['effective_start']);
            return $event;
        }, $events);
    }
}