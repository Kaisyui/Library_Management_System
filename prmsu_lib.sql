-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 15, 2025 at 05:16 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `prmsu_lib`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `admin_id` varchar(50) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_initial` varchar(5) DEFAULT NULL,
  `password` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin`
--

INSERT INTO `admin` (`admin_id`, `last_name`, `first_name`, `middle_initial`, `password`) VALUES
('ADMIN001', 'Reyes', 'Maria', 'L', 'admin123');

-- --------------------------------------------------------

--
-- Table structure for table `books`
--

CREATE TABLE `books` (
  `book_code` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `author` varchar(150) DEFAULT NULL,
  `publisher` varchar(150) NOT NULL,
  `copies` int(11) NOT NULL DEFAULT 1,
  `category` varchar(100) DEFAULT NULL,
  `publication_year` int(11) DEFAULT NULL,
  `total_copies` int(11) DEFAULT 1,
  `available_copies` int(11) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `books`
--

INSERT INTO `books` (`book_code`, `title`, `author`, `publisher`, `copies`, `category`, `publication_year`, `total_copies`, `available_copies`) VALUES
('23-56-89995-56', 'Human Computer Interaction', 'Mr. Joseph Cortez', 'cbapa', 2, NULL, NULL, 1, 1),
('23-57-25874', 'Life of Rizal', 'Mr. Jose Rizal', 'History', 0, NULL, NULL, 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `borrow_records`
--

CREATE TABLE `borrow_records` (
  `borrow_id` int(11) NOT NULL,
  `student_id` varchar(50) DEFAULT NULL,
  `book_code` varchar(50) DEFAULT NULL,
  `borrow_date` date DEFAULT NULL,
  `return_date` date DEFAULT NULL,
  `status` enum('Borrowed','Returned','Overdue') DEFAULT 'Borrowed',
  `balance` decimal(10,2) DEFAULT 0.00,
  `payment_status` enum('Paid','Unpaid') DEFAULT 'Unpaid',
  `payment` decimal(10,2) DEFAULT 0.00,
  `due_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `borrow_records`
--

INSERT INTO `borrow_records` (`borrow_id`, `student_id`, `book_code`, `borrow_date`, `return_date`, `status`, `balance`, `payment_status`, `payment`, `due_date`) VALUES
(3, '125-25-02', '23-57-25874', '2025-11-15', NULL, 'Overdue', 0.00, 'Unpaid', 0.00, '2025-11-15'),
(4, '125-25-02', '23-56-89995-56', '2025-11-15', NULL, 'Borrowed', 0.00, 'Unpaid', 0.00, '2025-11-16');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `payment_id` int(11) NOT NULL,
  `student_id` varchar(50) NOT NULL,
  `borrow_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `method` varchar(50) DEFAULT 'cash',
  `paid_at` datetime DEFAULT current_timestamp(),
  `note` text DEFAULT NULL,
  `transaction_ref` varchar(255) DEFAULT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'Pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `student_id` varchar(50) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_initial` varchar(5) DEFAULT NULL,
  `course` varchar(100) DEFAULT NULL,
  `year_level` varchar(20) DEFAULT NULL,
  `contact` int(11) DEFAULT NULL,
  `password` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`student_id`, `last_name`, `first_name`, `middle_initial`, `course`, `year_level`, `contact`, `password`) VALUES
('125-25-01', 'Penduco', 'Pedro', '', 'BSBA', '1', 2147483647, 'student01'),
('125-25-02', 'Dela Cruz', 'Diane', '', 'BS Mechanical Engineering', '4A', 2147483647, 'student02'),
('125-25-03', 'Artates', 'Jherzel', '', 'BS Information Technology', '3A', 2147483647, 'student30');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`admin_id`);

--
-- Indexes for table `books`
--
ALTER TABLE `books`
  ADD PRIMARY KEY (`book_code`);

--
-- Indexes for table `borrow_records`
--
ALTER TABLE `borrow_records`
  ADD PRIMARY KEY (`borrow_id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `book_code` (`book_code`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`payment_id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`student_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `borrow_records`
--
ALTER TABLE `borrow_records`
  MODIFY `borrow_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `borrow_records`
--
ALTER TABLE `borrow_records`
  ADD CONSTRAINT `borrow_records_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `borrow_records_ibfk_2` FOREIGN KEY (`book_code`) REFERENCES `books` (`book_code`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
