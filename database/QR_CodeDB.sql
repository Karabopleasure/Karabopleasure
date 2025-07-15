SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

-- Table for Lecturers
CREATE TABLE Lecturers (
    LecturerID INT PRIMARY KEY AUTO_INCREMENT,
    Name VARCHAR(255) NOT NULL,
    Email VARCHAR(255) UNIQUE NOT NULL,
    PasswordHash VARCHAR(255) NOT NULL,
    InviteToken VARCHAR(255) NOT NULL, -- Unique token for password setup
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ResetToken VARCHAR(255) NULL,
    ResetTokenExpiry DATETIME NULL
);

CREATE TABLE Lecturers (
    LecturerID INT PRIMARY KEY AUTO_INCREMENT,
    Name VARCHAR(255) NOT NULL,
    Email VARCHAR(255) UNIQUE NOT NULL,
    PasswordHash VARCHAR(255),  -- NULL initially, set when they create a password
    InviteToken VARCHAR(255) NOT NULL, -- Unique token for password setup
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE admins (
    AdminID INT PRIMARY KEY AUTO_INCREMENT,
    Email VARCHAR(255) UNIQUE NOT NULL,
    Password VARCHAR(255) NOT NULL
);



-- Table for Modules
CREATE TABLE Modules (
    ModuleID INT PRIMARY KEY AUTO_INCREMENT,
    LecturerID INT NOT NULL,
    ModuleName VARCHAR(255) NOT NULL,
    FOREIGN KEY (LecturerID) REFERENCES Lecturers(LecturerID) ON DELETE CASCADE
);

CREATE TABLE Sessions (
    SessionID INT AUTO_INCREMENT PRIMARY KEY,
    SessionName VARCHAR(255) NOT NULL,
    ModuleID INT NOT NULL,
    SessionDate DATE NOT NULL,
    StartTime DATETIME NOT NULL,
    EndTime DATETIME NOT NULL,
    FOREIGN KEY (ModuleID) REFERENCES Modules(ModuleID) ON DELETE CASCADE
);

CREATE TABLE Students(
    StudentID INT AUTO_INCREMENT PRIMARY KEY,
    StudentName VARCHAR(255) NOT NULL,
    StudentNumber VARCHAR(30) NOT NULL,
    LecturerID INT NOT NULL,
    ModuleID INT NOT NULL,
    FOREIGN KEY (LecturerID) REFERENCES Lecturers(LecturerID) ON DELETE CASCADE,
    FOREIGN KEY (ModuleID) REFERENCES Modules(ModuleID) ON DELETE CASCADE
)



CREATE TABLE Sessions (
    SessionID INT AUTO_INCREMENT PRIMARY KEY,
    SessionName VARCHAR(255) NOT NULL,
    ModuleID INT NOT NULL,
    SessionDate DATE NOT NULL,
    StartTime DATETIME NOT NULL,
    EndTime DATETIME NOT NULL,
    QRCodeFile VARCHAR(255), -- Stores the file path of the generated QR code
    QRCodeLink VARCHAR(500), -- Stores the URL link to the attendance form
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP, -- Tracks session creation time
    UpdatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, -- Updates when modified
    FOREIGN KEY (ModuleID) REFERENCES Modules(ModuleID) ON DELETE CASCADE
);


ALTER TABLE Sessions 
ADD COLUMN QRCodeFile VARCHAR(255) AFTER EndTime, 
ADD COLUMN QRCodeLink VARCHAR(500) AFTER QRCodeFile, 
ADD COLUMN CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER QRCodeLink, 
ADD COLUMN UpdatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER CreatedAt; 


-- Table for Attendance Records

CREATE TABLE Attendance (
    AttendanceID INT PRIMARY KEY AUTO_INCREMENT,
    ModuleID INT NOT NULL,
    SessionID INT NOT NULL,
    StudentNumber VARCHAR(20) NOT NULL,
    StudentName VARCHAR(200) NOT NULL,
    Timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ModuleID) REFERENCES Modules(ModuleID) ON DELETE CASCADE,
    FOREIGN KEY (SessionID) REFERENCES Sessions(SessionID) ON DELETE CASCADE
);

CREATE TABLE Attendance (
    AttendanceID INT PRIMARY KEY AUTO_INCREMENT,
    ModuleID INT NOT NULL,
    SessionID INT NOT NULL,
    StudentNumber VARCHAR(20) NOT NULL,
    StudentName VARCHAR(200) NOT NULL,
    DeviceID VARCHAR(50) NOT NULL, -- Store the unique device identifier
    Timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ModuleID) REFERENCES Modules(ModuleID) ON DELETE CASCADE,
    FOREIGN KEY (SessionID) REFERENCES Sessions(SessionID) ON DELETE CASCADE,
    UNIQUE (SessionID, StudentNumber), -- Prevent duplicate student submissions per session
    UNIQUE (SessionID, DeviceID) -- Prevent multiple submissions from the same device per session
);


CREATE TABLE QR_Tokens (
    TokenID INT AUTO_INCREMENT PRIMARY KEY,      
    SessionID INT NOT NULL,                      
    Token VARCHAR(32) NOT NULL,                  
    Used TINYINT(1) DEFAULT 0,                  
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP, 
    FOREIGN KEY (SessionID) REFERENCES Sessions(SessionID) ON DELETE CASCADE  
);

-- Table for QR Codes
CREATE TABLE QR_Codes (
    QRCodeID INT PRIMARY KEY AUTO_INCREMENT,
    ModuleID INT NOT NULL,
    QRCodeText VARCHAR(255) NOT NULL,
    GeneratedAt DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ModuleID) REFERENCES Modules(ModuleID) ON DELETE CASCADE
);


