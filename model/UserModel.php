<?php
class UserModel {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function registerUser($prenom, $nom, $email, $password, $role = 'employer', $leave_balance = 28) {
        try {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                error_log("Invalid email format in registerUser: $email");
                return false;
            }
            if (empty($prenom) || empty($nom)) {
                error_log("Empty prenom or nom in registerUser: $prenom, $nom");
                return false;
            }

            $hashedPassword = $password === null ? null : password_hash($password, PASSWORD_DEFAULT);

            // Check for duplicate email
            $existingUser = $this->getUserByEmail($email);
            if ($existingUser) {
                error_log("Duplicate email found: $email");
                return false;
            }

            $stmt = $this->db->prepare("INSERT INTO user (prenom, nom, email, password, role, leave_balance) VALUES (?, ?, ?, ?, ?, ?)");
            $success = $stmt->execute([$prenom, $nom, $email, $hashedPassword, $role, $leave_balance]);
            if (!$success) {
                error_log("SQL execution failed: " . print_r($stmt->errorInfo(), true));
            }
            return $success;
        } catch (PDOException $e) {
            error_log("RegisterUser PDO error: " . $e->getMessage() . " - Query: " . $stmt->queryString);
            return false;
        }
    }

    public function getUserByEmail($email) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM user WHERE email = ?");
            $stmt->execute([$email]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("GetUserByEmail error: " . $e->getMessage());
            return null;
        }
    }

    public function updatePasswordForNull($email, $password) {
        try {
            if (strlen($password) < 8) {
                return false;
            }
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $this->db->prepare("UPDATE user SET password = ? WHERE email = ? AND password IS NULL");
            return $stmt->execute([$hashedPassword, $email]);
        } catch (PDOException $e) {
            error_log("UpdatePasswordForNull error: " . $e->getMessage());
            return false;
        }
    }

    public function storeResetToken($email, $token, $expiry) {
        try {
            $stmt = $this->db->prepare("UPDATE user SET reset_token = ?, token_expiry = ? WHERE email = ?");
            return $stmt->execute([$token, $expiry, $email]);
        } catch (PDOException $e) {
            error_log("StoreResetToken error: " . $e->getMessage());
            return false;
        }
    }

    public function getUserByToken($token) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM user WHERE reset_token = ? AND token_expiry > NOW()");
            $stmt->execute([$token]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("GetUserByToken error: " . $e->getMessage());
            return null;
        }
    }

    public function updatePassword($email, $newPassword) {
        try {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $this->db->prepare("UPDATE user SET password = ?, reset_token = NULL, token_expiry = NULL WHERE email = ?");
            return $stmt->execute([$hashedPassword, $email]);
        } catch (PDOException $e) {
            error_log("UpdatePassword error: " . $e->getMessage());
            return false;
        }
    }

    public function findUserByEmail($email) {
        return $this->getUserByEmail($email) !== null;
    }

    public function getLeaveBalance($email) {
        try {
            $stmt = $this->db->prepare("SELECT leave_balance FROM user WHERE email = ?");
            $stmt->execute([$email]);
            return $stmt->fetchColumn() ?: 0;
        } catch (PDOException $e) {
            error_log("GetLeaveBalance error: " . $e->getMessage());
            return 0;
        }
    }

    public function updateLeaveBalance($email, $days) {
        try {
            $this->db->beginTransaction();
            $currentBalance = $this->getLeaveBalance($email);
            if ($currentBalance < $days) {
                $this->db->rollBack();
                return false;
            }

            $stmt = $this->db->prepare("UPDATE user SET leave_balance = leave_balance - ? WHERE email = ?");
            $success = $stmt->execute([$days, $email]);
            if ($success) {
                $this->db->commit();
                return $this->getLeaveBalance($email);
            } else {
                $this->db->rollBack();
                return false;
            }
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("UpdateLeaveBalance error: " . $e->getMessage());
            return false;
        }
    }

    public function getUserRole($email) {
        try {
            $stmt = $this->db->prepare("SELECT role FROM user WHERE email = ?");
            $stmt->execute([$email]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? $result['role'] : null;
        } catch (PDOException $e) {
            error_log("GetUserRole error: " . $e->getMessage());
            return null;
        }
    }

    public function updateUserRole($email, $newRole) {
        try {
            $stmt = $this->db->prepare("UPDATE user SET role = ? WHERE email = ?");
            return $stmt->execute([$newRole, $email]);
        } catch (PDOException $e) {
            error_log("UpdateUserRole error: " . $e->getMessage());
            return false;
        }
    }
   public function getUserTheme($email) {
    try {
        $stmt = $this->db->prepare("SELECT theme FROM user WHERE email = ?");
        $stmt->execute([$email]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['theme'] : 'light';
    } catch (PDOException $e) {
        error_log("GetUserTheme error: " . $e->getMessage());
        return 'light';
    }
}
// Update user theme
public function updateUserTheme($email, $theme) {
    try {
        if (!in_array($theme, ['light', 'dark'])) {
            error_log("Invalid theme value: $theme");
            return false;
        }
        $stmt = $this->db->prepare("UPDATE user SET theme = ? WHERE email = ?");
        return $stmt->execute([$theme, $email]);
    } catch (PDOException $e) {
        error_log("UpdateUserTheme error: " . $e->getMessage());
        return false;
    }
}
// Update user profile picture
    public function updateProfilePicture($email, $filePath) {
        $query = "UPDATE user SET profile_picture = :filePath WHERE email = :email";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':filePath', $filePath);
        $stmt->bindParam(':email', $email);
        return $stmt->execute();
    }
    

}