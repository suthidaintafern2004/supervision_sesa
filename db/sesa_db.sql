-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 26, 2026 at 12:15 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `sesa_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `certificate_log`
--

CREATE TABLE `certificate_log` (
  `id` int(10) UNSIGNED NOT NULL COMMENT 'รหัสใบเกียรติบัตร',
  `p_id` varchar(13) NOT NULL COMMENT 'รหัสบัตรปชช. ศน.',
  `t_pid` varchar(13) NOT NULL COMMENT 'รหัสบัตรปชช. ครู',
  `subject_code` varchar(50) NOT NULL COMMENT 'รหัสวิชา',
  `inspection_time` int(11) NOT NULL COMMENT 'ครั้งที่นิเทศ',
  `generated_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'วันที่ออกเกียรติบัตร',
  `academic_year` int(11) NOT NULL COMMENT 'ปีการศึกษา',
  `form_type` varchar(50) DEFAULT NULL COMMENT 'ประเภทแบบฟอร์ม'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `images`
--

CREATE TABLE `images` (
  `id` int(11) NOT NULL COMMENT 'รหัสรูปภาพ',
  `p_id` varchar(13) NOT NULL COMMENT 'รหัสบัตรปชช. ศน.',
  `t_pid` varchar(13) NOT NULL COMMENT 'รหัสบัตรปชช. ครู',
  `subject_code` varchar(50) DEFAULT NULL COMMENT 'รหัสวิชา',
  `inspection_time` int(11) DEFAULT NULL COMMENT 'ครั้งที่นิเทศ',
  `file_name` varchar(255) NOT NULL COMMENT 'ชื่อรูปภาพ',
  `uploaded_on` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'วันเวลาที่อัปโหลดรูป',
  `academic_year` int(11) NOT NULL COMMENT 'ปีการศึกษา',
  `form_type` varchar(50) DEFAULT NULL COMMENT 'ประเภทแบบฟอร์มที่อัปโหลด'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `kpi_answers`
--

CREATE TABLE `kpi_answers` (
  `question_id` int(11) NOT NULL COMMENT 'รหัสหัวข้อการนิเทศ',
  `rating_score` int(11) DEFAULT NULL COMMENT 'คะแนน',
  `p_id` varchar(13) NOT NULL COMMENT 'เลขบัตร ศน.',
  `t_pid` varchar(13) NOT NULL COMMENT 'เลขบัตร ครู',
  `subject_code` varchar(10) NOT NULL COMMENT 'รหัสวิชา',
  `inspection_time` int(11) NOT NULL COMMENT 'ครั้งที่นิเทศ',
  `supervision_date` datetime NOT NULL COMMENT 'วันเวลาที่บันทึก',
  `academic_year` int(11) NOT NULL COMMENT 'ปีการศึกษา'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `kpi_indicators`
--

CREATE TABLE `kpi_indicators` (
  `id` int(11) NOT NULL COMMENT 'รหัสตัวชี้วัด',
  `title` varchar(255) NOT NULL COMMENT 'ชื่อตัวชี้วัด',
  `display_order` int(11) NOT NULL DEFAULT 0 COMMENT 'ลำดับการแสดงผล'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `kpi_indicators`
--

INSERT INTO `kpi_indicators` (`id`, `title`, `display_order`) VALUES
(1, 'ตัวชี้วัดที่ 1 ผู้เรียนสามารถ เข้าถึงสิ่งเรียนและ เข้าใจบทเรียน / กิจกรรม', 1),
(2, 'ตัวชี้วัดที่ 2 ผู้เรียนสามารถ เชื่อมโยงความรู้หรือประสปกรณ์เดิมกับการเรียนรู้ใหม่', 2),
(3, 'ตัวชี้วัดที่ 3 ผู้เรียนได้สร้าง ความรู้เอง หรือสร้างประสปการณ์ใหม่จากการเรียนรู้', 3),
(4, 'ตัวชี้วัดที่ 4 ผู้เรียนได้รับการกระตุ้นและเกิดแรงจูงใจในการเรียนรู้ ', 4),
(5, 'ตัวชี้วัดที่ 5 ผู้เรียนได้รับการพัฒนาทักษะความเชี่ยวชาญจากการเรียนรู้ ', 5),
(6, 'ตัวชี้วัดที่ 6 ผู้เรียนได้รับข้อมูลสะท้อนกลับเพื่อปรับปรุงการเรียนรู้ ', 6),
(7, 'ตัวชี้วัดที่ 7 ผู้เรียนได้รับการพัฒนาการเรียนรู้ในบรรยากาศชั้นเรียน / การจัดกิจกรรมที่เหมาะสม ', 7),
(8, 'ตัวชี้วัดที่ 8 ผู้เรียนสามารถ กำกับการเรียนรู้และมีการเรียนรู้แบบนำตนเอง / กำหนดเป้าหมายการเรียนรู้และปฏิบัติกิจกรรมด้วยตนเองหรือกระบวนการกลุ่ม (เฉพาะกิจกรรมพัฒนาผู้เรียน) ', 8);

-- --------------------------------------------------------

--
-- Table structure for table `kpi_indicator_suggestions`
--

CREATE TABLE `kpi_indicator_suggestions` (
  `indicator_id` int(11) NOT NULL COMMENT 'รหัสตัวชี้วัด',
  `suggestion_text` text DEFAULT NULL COMMENT 'ข้อค้นพบ',
  `p_id` varchar(13) NOT NULL COMMENT 'รหัสปชช. ศน.',
  `t_pid` varchar(13) NOT NULL COMMENT 'รหัสปชช. ครู',
  `subject_code` varchar(50) NOT NULL COMMENT 'รหัสวิชา',
  `inspection_time` int(11) NOT NULL COMMENT 'ครั้งที่นิเทศ',
  `supervision_date` datetime DEFAULT NULL COMMENT 'วันที่บันทึการนิเทศก์',
  `academic_year` int(11) NOT NULL COMMENT 'ปีการศึกษา'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `kpi_questions`
--

CREATE TABLE `kpi_questions` (
  `id` int(11) NOT NULL,
  `indicator_id` int(11) NOT NULL COMMENT 'รหัสตัวชี้วัด',
  `question_text` text NOT NULL COMMENT 'ข้อคำถาม',
  `display_order` int(11) NOT NULL DEFAULT 0 COMMENT 'ลำดับการแสดงผลของคำถามในตัวชี้วัดนั้นๆ'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `kpi_questions`
--

INSERT INTO `kpi_questions` (`id`, `indicator_id`, `question_text`, `display_order`) VALUES
(1, 1, '1.1 เนื้อหา (Content) พร้อมโน้ตทัศน์ที่จัดให้ผู้เรียนเรียนรู้ หรือฝึกฝน มีความถูกต้อง และ ตรงตามหลักสูตร', 1),
(2, 1, '1.2 ออกแบบและจัดโครงสร้างบทเรียนเป็นระบบและใช้เวลาเหมาะสม', 2),
(3, 1, '1.3 ใช้สื่อประกอบบทเรียนได้เหมาะสมและช่วยในการเรียนรู้บรรลุวัตถุประสงค์ของบทเรียน', 3),
(4, 2, '2.1 มีการทบทวนความรู้ ทักษะ หรือประสปการณ์ เดิม เช่น การใช้คำถาม แบบฝึก หรือกิจกรรม ฯลฯ', 1),
(5, 2, '2.2 มีการเข้าถึงผู้เรียนที่ยังไม่พร้อมที่จะเรียนรู้ใหม่ ', 2),
(6, 2, '2.3 มีการช่วยเหลือผู้เรียนที่ยังมีความรู้ ทักษะ หรือ ประสปการณ์เดิมไม่เพียงพอที่จะเชื่อมโยงกับ การเรียนรู้ใหม่ เช่น การอธิบาย ยกตัวอย่าง การใช้คำถาม เกม หรือกิจกรรม ฯลฯ ', 3),
(7, 3, '3.1 ออกแบบงานหรือกิจกรรมให้ผู้เรียนสร้างความรู้หรือประสปการณ์ใหม่อย่างเหมาะสมกับวัยสภาพ และบริบทของผู้เรียนและชั้นเรียน', 1),
(8, 3, '3.2 ผู้เรียนได้ลงมือปฏิบัติกิจกรรมที่ต้องใช้ความรู้หรือทักษะหลากหลาย', 2),
(9, 3, '3.3 ใช้เทคนิคให้ผู้เรียนสรุปความรู้ หรือ ประสปการณ์ใหม่ด้วยตนเอง และสื่อสารความเข้าใจที่เชื่อมโยงองค์ความรู้ เช่น แผนที่ความคิด ตารางวิเคราะห์ การทดลองปฏิบัติ การนำเสนอ ฯลฯ', 3),
(10, 4, '4.1 กิจกรรมการเรียนรู้เชื่อมโยงสอดคล้องกับ ชีวิตประจำวัน บริบทชุมชน หรือสภาพจริงที่มีความหมายกับผู้เรียน', 1),
(11, 4, '4.2 วิธีหรือกิจกรรมการเรียนรู้ มีความท้าทาย และมีระดับความยากง่ายเหมาะสมกับวัย สภาพ และพัฒนาการของผู้เรียน', 2),
(12, 4, '4.3 ผู้เรียนมีโอกาศสะท้อนการเรียนรู้ แลกเปลี่ยน เรียนรู้ นำเสนอความสำเร็จ หรือ อธิบายข้อผิดพลาด/ความล้มเหลวที่เกิดขึ้นจากการเรียนรู้/การปฏิบัติกิจกรรม', 3),
(13, 5, '5.1 ผู้เรียนได้ฝึกทักษะต่าง ๆ ครบถ้วนตามวัตถุประสงค์การเรียนรู้ / การจัดกิจกรรม ', 1),
(14, 5, '5.2 ผู้เรียนได้บูรณาการทักษะต่างๆ ลงสู่การปฏิบัติกิจกรรมการเรียนรู้ ', 2),
(15, 5, '5.3 ผู้เรียนได้ประยุกต์ใช้ทักษะที่ได้รับการพัฒนาในสถานการณ์หรือการแก้ปัญหาใหม่ๆ ', 3),
(16, 6, '6.1 มีการสังเกตุหรือค้นหาข้อผิดพลาดในการปฏิบัติหรือมโนทัศน์ที่คลาดเคลื่อนของผู้เรียนในระหว่างการเรียนรู้ ', 1),
(17, 6, '6.2 มีการประเมินผลระหว่างการเรียนรู้โดยใช้วิธีการที่เหมาะสม เช่น การใช้คำถาม แบบทดสอบ การปฏิบัติ ฯลฯ', 2),
(18, 6, '6.3 มีการนำผลการสังเกตุ หรือผลการค้นหา หรือ ผลการประเมินระหว่างเรียนรู้สะท้อนกลับให้ผู้เรียน', 3),
(19, 7, '7.1  ผู้เรียนได้รับแบบอย่างที่ดีในการใช้ภาษาพฤติกรรมแสดงออก และเจตคติจากครูผู้สอน', 1),
(20, 7, '7.2  กระตุ้นให้ผู้เรียนมั่นใจ มีอิสระในการคิด หรือ ทดลอง และรับความรู้ความสามารถของตนเอง', 2),
(21, 7, '7.3  ใช้สื่อการเรียนหรือตัวอย่างประกอบที่หลากหลาย และกระตุ้นให้ผู้เรียนคิดวิเคราะห์เปรียบเทียบจากสื่อการเรียนหรือตัวอย่าง', 3),
(22, 8, '8.1 ผู้เรียนได้รับโอกาสในการกำหนดเป้าหมายการเรียนรู้หรือการลงมือปฏิบัติ /การปฏิบัติกิจกรรมด้วยตนเองหรือกระบวนการกลุ่ม', 1),
(23, 8, '8.2 ผู้เรียนได้ประเมินตนเองหรือถูกเพื่อนประเมินในระหว่างเรียน หรือ เมื่อจบบทเรียน / ระหว่างปฏิบัติหรือเมื่อภายหลังจบกิจกรรม', 2),
(24, 8, '8.3 ผู้เรียนได้รับการกระตุ้นหรือการมอบหมายงานให้ศึกษา ค้นคว้า ฝึกฝน หรือเรียนรู้ต่อเนื่องเพิ่มเติมภายหลังจบบทเรียน /ภายหลังจบกิจกรรม', 3);

-- --------------------------------------------------------

--
-- Table structure for table `office`
--

CREATE TABLE `office` (
  `office_id` int(11) NOT NULL COMMENT 'รหัสสังกัด',
  `office_name` varchar(255) DEFAULT NULL COMMENT 'ชื่อสังกัด'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `office`
--

INSERT INTO `office` (`office_id`, `office_name`) VALUES
(1000520001, 'สพม.ลำปาง ลำพูน');

-- --------------------------------------------------------

--
-- Table structure for table `position`
--

CREATE TABLE `position` (
  `position_id` int(11) NOT NULL COMMENT 'รหัสตำแหน่ง',
  `position_name` varchar(255) DEFAULT NULL COMMENT 'ชื่อตำแหน่ง'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `position`
--

INSERT INTO `position` (`position_id`, `position_name`) VALUES
(1, 'ครู'),
(2, 'ครูอัตราจ้าง / พนักงานราชการ'),
(3, 'ผู้อำนวยการโรงเรียน'),
(4, 'รองผู้อำนวยการโรงเรียน'),
(5, 'ศึกษานิเทศก์'),
(6, 'จิตวิทยาโรงเรียนประจำ สพม.'),
(7, 'ไม่มีตำแหน่ง');

-- --------------------------------------------------------

--
-- Table structure for table `prefix`
--

CREATE TABLE `prefix` (
  `prefix_id` int(11) NOT NULL COMMENT 'รหัสคำนำหน้าชื่อ',
  `prefix_name` varchar(255) DEFAULT NULL COMMENT 'คำนำหน้าชื่อ'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `prefix`
--

INSERT INTO `prefix` (`prefix_id`, `prefix_name`) VALUES
(3, 'นาย'),
(4, 'นางสาว'),
(5, 'นาง'),
(219, 'ว่าที่ร้อยตรี'),
(223, 'สิบเอก'),
(981, 'ว่าที่ร้อยตรีหญิง');

-- --------------------------------------------------------

--
-- Table structure for table `quickwin_options`
--

CREATE TABLE `quickwin_options` (
  `OptionID` int(11) NOT NULL COMMENT 'รหัสหัวข้อเลือก',
  `OptionText` varchar(500) NOT NULL COMMENT 'หัวข้อเลือก'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `quickwin_options`
--

INSERT INTO `quickwin_options` (`OptionID`, `OptionText`) VALUES
(1, 'ปลูกฝังความรักในสถาบันหลักของชาติ และน้อมนำพระบรมราโชบายด้านการศึกษาสู่การปฏิบัติ'),
(2, 'ส่งเสริมและพัฒนาการจัดการเรียนรู้ ภูมิศาสตร์ ประวัติศาสตร์ หน้าที่พลเมือง ศีลธรรม และประชาธิปไตย'),
(3, 'ปรับกระบวนการจัดการเรียนรู้ให้หลากหลาย ด้วยเทคโนโลยีที่ทันสมัย'),
(4, 'ส่งเสริมการอ่านให้เป็นวิถีปฏิบัติ เพื่อให้ผู้เรียนค้นหาและพัฒนาต่อยอดองค์ความรู้ อย่างต่อเนื่อง'),
(5, 'ส่งเสริม สนับสนุนกิจกรรมพัฒนาผู้เรียน'),
(6, 'พัฒนาการจัดการศึกษาสำหรับเด็กที่มีความต้องการจำเป็นพิเศษ'),
(7, 'ส่งเสริมศักยภาพผู้เรียนรายบุคคลสู่ความเป็นเลิศ'),
(8, 'เสริมสร้างความปลอดภัยของผู้เรียน ครูและบุคลากรทางการศึกษา และสถานศึกษา'),
(9, 'เพิ่มโอกาสและสร้างความเสมอภาคทางการศึกษา'),
(10, 'พัฒนาครูและบุคลากรทางการศึกษาให้มีความรู้ ความสามารถ และทักษะที่ทันสมัย'),
(11, 'จัดการเรียนรู้และวัดประเมินผลที่มุ่งเน้นพัฒนาการตามศักยภาพผู้เรียนรายบุคคล'),
(12, 'พัฒนาระบบบริหารจัดการให้มีประสิทธิภาพ ถูกต้อง รวดเร็ว ประโยชน์ ประหยัด โปร่งใสและตรวจสอบได้'),
(13, 'ระบบนิเทศภายในสถานศึกษา'),
(14, 'ลดภาระครู'),
(15, 'เพิ่มสวัสดิการครูและบุคลากรทางการศึกษา'),
(16, 'ส่งเสริมการเรียนดี มีคุณธรรม'),
(17, 'ส่งเสริม สนับสนุน พัฒนาหลักสูตรสถานศึกษา'),
(18, 'ส่งเสริมและพัฒนากระบวนการเรียนรู้'),
(19, 'ส่งเสริมและพัฒนาการประกันคุณภาพการศึกษา'),
(20, 'ส่งเสริมและพัฒนาการวัดและประเมินผลการศึกษา'),
(21, 'โรงเรียนขนาดเล็ก');

-- --------------------------------------------------------

--
-- Table structure for table `quickwin_satisfaction_answers`
--

CREATE TABLE `quickwin_satisfaction_answers` (
  `t_pid` varchar(13) NOT NULL COMMENT 'รหัสปชช. ครู',
  `p_id` varchar(13) NOT NULL COMMENT 'รหัสปชช. ศน.',
  `supervision_date` datetime NOT NULL COMMENT 'วันเวลาที่บันทึก',
  `academic_year` int(11) NOT NULL COMMENT 'ปีการศึกษา',
  `question_id` int(11) NOT NULL COMMENT 'รหัสหัวข้อคำถาม',
  `rating` int(11) NOT NULL COMMENT 'คะแนน 1 - 5'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `quick_win`
--

CREATE TABLE `quick_win` (
  `t_pid` varchar(13) NOT NULL COMMENT 'รหัสบัตรปชช. ครู',
  `p_id` varchar(13) NOT NULL COMMENT 'รหัสบัตรปชช. ศน.',
  `supervision_date` datetime NOT NULL COMMENT 'เวลาที่บันทึกแบบฟอร์ม',
  `academic_year` int(11) NOT NULL COMMENT 'ปีการศึกษา',
  `semester` tinyint(4) NOT NULL COMMENT 'ภาคเรียน',
  `options` varchar(255) NOT NULL COMMENT 'ข้อหัวการนิเทศที่เลือก',
  `option_other` text NOT NULL COMMENT 'หัวข้อการนิเทศอื่นๆ(พิมพ์)',
  `satisfaction_suggestion` text DEFAULT NULL COMMENT 'ข้อเสนอเเนะความพึงพอใจ',
  `satisfaction_date` datetime DEFAULT NULL COMMENT 'เวลาที่ประเมินความพึงพอใจ',
  `satisfaction_submitted` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'สถานะประเมินความพึงพอใจ',
  `updated_at` datetime DEFAULT NULL COMMENT 'วันเวลาที่อัปเดต',
  `deleted_at` datetime DEFAULT NULL COMMENT 'วันเวลาที่ลบ'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ranks`
--

CREATE TABLE `ranks` (
  `rank_id` int(11) NOT NULL COMMENT 'รหัสวิทยฐานะ (Primary Key)',
  `rank_name` varchar(50) DEFAULT NULL COMMENT 'ชื่อวิทยฐานะ'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ranks`
--

INSERT INTO `ranks` (`rank_id`, `rank_name`) VALUES
(1, 'ชำนาญการ'),
(2, 'ชำนาญการพิเศษ'),
(3, 'เชี่ยวชาญ'),
(4, 'เชี่ยวชาญพิเศษ'),
(5, 'ไม่มีวิทยฐานะ');

-- --------------------------------------------------------

--
-- Table structure for table `satisfaction_answers`
--

CREATE TABLE `satisfaction_answers` (
  `question_id` int(11) NOT NULL COMMENT 'รหัสหัวข้อคำถาม',
  `rating` int(11) NOT NULL COMMENT 'คะแนนความพึงพอใจ',
  `p_id` varchar(13) NOT NULL COMMENT 'รหัสปชช. ศน.',
  `t_pid` varchar(13) NOT NULL COMMENT 'รหัสปชช. ครู',
  `subject_code` varchar(50) NOT NULL COMMENT 'รหัสวิชา',
  `inspection_time` int(11) NOT NULL COMMENT 'ครั้งที่นิเทศ',
  `academic_year` int(11) NOT NULL COMMENT 'ปีการศึกษา'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `satisfaction_questions`
--

CREATE TABLE `satisfaction_questions` (
  `id` int(11) NOT NULL COMMENT 'รหัสคำถามความพึงพอใจ',
  `question_text` text NOT NULL COMMENT 'คำถามความพึงพอใจ'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `satisfaction_questions`
--

INSERT INTO `satisfaction_questions` (`id`, `question_text`) VALUES
(1, 'ความรู้ ความสามารถ ของผู้นิเทศในเรื่องที่นิเทศ					'),
(2, 'เทคนิค วิธีการ ที่ผู้นิเทศใช้ในการนิเทศ					'),
(3, 'ภาวะผู้นำทางวิชาการ และผู้ตามที่ดีของผู้นิเทศ'),
(4, 'มนุษยสัมพันธ์ที่ดีของผู้นิเทศ'),
(5, 'การวางตัวและการปฏิบัติตนของผู้นิเทศ'),
(6, 'บุคลิกภาพ และการใช้ภาษา คำพูดทางบวกในการนิเทศ					'),
(7, 'ขั้นตอนที่ใช้ในการนิเทศมีความเหมาะสม					'),
(8, 'เรื่องที่นิเทศตรงตามความต้องการของผู้รับการนิเทศ หรือนโยบายและจุดเน้นของสพฐ.ประจำปีงบประมาณ พ.ศ. 2569 – 2570 และนโยบายระยะเร่งด่วน (Quick Win) ประจำปีงบประมาณ พ.ศ. 2569'),
(9, 'ระยะเวลาที่ใช้ในการนิเทศ'),
(10, 'ผู้นิเทศให้ข้อเสนอแนะแนวทางที่ชัดเจน'),
(11, 'ผลการนิเทศสามารถไปปรับประยุกต์ใช้ในการพัฒนาหรือนำไปปฏิบัติได้'),
(12, 'ผู้นิเทศยอมรับฟังความคิดเห็นของผู้รับการนิเทศ'),
(13, 'ความเป็นกัลยาณมิตรของผู้นิเทศ'),
(14, 'ความพึงพอใจต่อการนิเทศโดยภาพรวม'),
(15, 'ควรให้มีการนิเทศในโอกาสต่อไป');

-- --------------------------------------------------------

--
-- Table structure for table `school`
--

CREATE TABLE `school` (
  `school_id` int(11) NOT NULL COMMENT 'รหัสโรงเรียน',
  `school_name` varchar(255) DEFAULT NULL COMMENT 'ชื่อโรงเรียน'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `school`
--

INSERT INTO `school` (`school_id`, `school_name`) VALUES
(1051510293, 'จักรคำคณาทร จังหวัดลำพูน'),
(1051510294, 'ส่วนบุญโญปถัมภ์ ลำพูน'),
(1051510295, 'อุโมงค์วิทยาคม'),
(1051510296, 'บ้านแป้นพิทยาคม'),
(1051510297, 'แม่ทาวิทยาคม'),
(1051510298, 'ทาขุมเงินวิทยาคาร'),
(1051510299, 'ธีรกานท์บ้านโฮ่ง'),
(1051510300, 'บ้านโฮ่งรัตนวิทยา'),
(1051510301, 'เวียงเจดีย์วิทยา'),
(1051510302, 'แม่ตืนวิทยา'),
(1051510304, 'ทุ่งหัวช้างพิทยาคม'),
(1051510305, 'ป่าซาง'),
(1051510306, 'วชิรป่าซาง'),
(1051510307, 'น้ำดิบวิทยาคม'),
(1051510309, 'ป่าตาลบ้านธิพิทยา'),
(1052032007, 'วิทยาศาสตร์จุฬาภรณราชวิทยาลัย ลำปาง'),
(1052500513, 'บุญวาทย์วิทยาลัย'),
(1052500514, 'ลำปางกัลยาณี'),
(1052500516, 'เตรียมอุดมศึกษาพัฒนาการเขลางค์นคร'),
(1052500517, 'กิ่วลมวิทยา'),
(1052500518, 'เสด็จวนชยางค์กูลวิทยา'),
(1052500519, 'โป่งหลวงวิทยา รัชมังคลาภิเษก'),
(1052500520, 'เมืองมายวิทยา'),
(1052500522, 'แม่เมาะวิทยา'),
(1052500523, 'สบจางวิทยา'),
(1052500524, 'เกาะคาวิทยาคม'),
(1052500525, 'ไหล่หินวิทยา'),
(1052500526, 'เสริมงามวิทยาคม'),
(1052500527, 'ประชารัฐธรรมคุณ'),
(1052500528, 'ประชาราชวิทยา'),
(1052500529, 'แจ้ห่มวิทยา'),
(1052500530, 'วังเหนือวิทยา'),
(1052500531, 'เถินวิทยา'),
(1052500532, 'เวียงมอกวิทยา'),
(1052500533, 'แม่พริกวิทยา'),
(1052500534, 'แม่ทะวิทยา'),
(1052500535, 'แม่ทะประชาสามัคคี'),
(1052500536, 'แม่ทะพัฒนศึกษา'),
(1052500537, 'สบปราบพิทยาคม'),
(1052500538, 'ห้างฉัตรวิทยา'),
(1052500539, 'แม่สันวิทยา'),
(1052500541, 'เมืองปานวิทยา'),
(1052500542, 'ทุ่งกว๋าววิทยาคม'),
(1052500543, 'เมืองปานพัฒนวิทย์'),
(1052500544, 'ทุ่งอุดมวิทยา');

-- --------------------------------------------------------

--
-- Table structure for table `site_views`
--

CREATE TABLE `site_views` (
  `id` int(11) NOT NULL,
  `total_views` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `site_views`
--

INSERT INTO `site_views` (`id`, `total_views`) VALUES
(1, 204);

-- --------------------------------------------------------

--
-- Table structure for table `subject_group`
--

CREATE TABLE `subject_group` (
  `subjectgroup_id` int(11) NOT NULL COMMENT 'รหัสกลุ่มสาระฯ',
  `subjectgroup_name` varchar(255) DEFAULT NULL COMMENT 'ชื่อกลุ่มสาระการเรียนรู้'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subject_group`
--

INSERT INTO `subject_group` (`subjectgroup_id`, `subjectgroup_name`) VALUES
(1, 'ภาษาไทย'),
(2, 'คณิตศาสตร์'),
(3, 'ภาษาต่างประเทศ'),
(4, 'วิทยาศาสตร์และเทคโนโลยี'),
(5, 'ศิลปะ'),
(6, 'สุขศึกษาและพลศึกษา'),
(7, 'สังคมศึกษา ศาสนา และวัฒนธรรม'),
(8, 'การงานอาชีพ'),
(9, 'กิจกรรมพัฒนาผู้เรียน'),
(10, 'ไม่มีกลุ่มสาระ');

-- --------------------------------------------------------

--
-- Table structure for table `supervision_sessions`
--

CREATE TABLE `supervision_sessions` (
  `p_id` varchar(13) NOT NULL COMMENT 'รหัสบัตรปชช. ศน.',
  `t_pid` varchar(13) NOT NULL COMMENT 'รหัสบัตรปชช. ครู',
  `subject_code` varchar(50) NOT NULL COMMENT 'รหัสวิชา',
  `subject_name` varchar(255) DEFAULT NULL COMMENT 'ชื่อวิชา',
  `inspection_time` int(11) NOT NULL COMMENT 'ครั้งที่นิเทศ',
  `inspection_date` date DEFAULT NULL COMMENT 'วันที่รับการนิเทศ',
  `overall_suggestion` text DEFAULT NULL COMMENT 'ข้อเสนอแนะเพิ่มเติม',
  `supervision_date` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'วันเวลาที่บันทึก',
  `academic_year` varchar(4) NOT NULL COMMENT 'ปีการศึกษา',
  `semester` tinyint(4) NOT NULL COMMENT 'ภาคเรียน',
  `deleted_at` datetime DEFAULT NULL COMMENT 'วันที่ลบ',
  `satisfaction_suggestion` text DEFAULT NULL COMMENT 'ข้อเสนอแนะจากแบบประเมินความพึงพอใจ',
  `satisfaction_submitted` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'สถานะการประเมิน: 0=ยังไม่ประเมิน, 1=ประเมินแล้ว',
  `satisfaction_date` datetime DEFAULT NULL COMMENT 'วันที่ประเมินความพึงพอใจ'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `supervisor`
--

CREATE TABLE `supervisor` (
  `p_id` varchar(13) NOT NULL COMMENT 'เลขบัตรประชาชน',
  `office_id` int(11) NOT NULL COMMENT 'รหัสสังกัด',
  `prefix_id` int(11) NOT NULL COMMENT 'รหัสคำนำหน้า',
  `fname` varchar(100) DEFAULT NULL COMMENT 'ชื่อ',
  `lname` varchar(100) DEFAULT NULL COMMENT 'นามสกุล',
  `position_id` int(11) NOT NULL COMMENT 'รหัสตำแหน่ง',
  `rank_id` int(11) NOT NULL COMMENT 'รหัสวิทยฐานะ',
  `role` varchar(20) NOT NULL DEFAULT 'supervisor' COMMENT 'สิทธิ์เข้าใช้งาน'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `supervisor`
--

INSERT INTO `supervisor` (`p_id`, `office_id`, `prefix_id`, `fname`, `lname`, `position_id`, `rank_id`, `role`) VALUES
('1100100000001', 1000520001, 3, 'สมชาย', 'ใจดี', 5, 3, 'supervisor'),
('1100100000002', 1000520001, 4, 'วิมล', 'สวยงาม', 5, 2, 'supervisor'),
('1100100000003', 1000520001, 3, 'อำนาจ', 'รุ่งเรือง', 5, 2, 'supervisor'),
('1100100000004', 1000520001, 5, 'ประภาศรี', 'มีสุข', 5, 3, 'supervisor'),
('1100100000005', 1000520001, 3, 'ธีรพล', 'ก้าวหน้า', 5, 2, 'supervisor'),
('1100100000006', 1000520001, 4, 'สุดาพร', 'สอนดี', 5, 2, 'supervisor'),
('1100100000007', 1000520001, 3, 'เกรียงไกร', 'ไวไว', 5, 2, 'supervisor'),
('1100100000008', 1000520001, 5, 'มณีรัตน์', 'รัตนใจ', 5, 3, 'supervisor'),
('1100100000009', 1000520001, 3, 'สมพงษ์', 'มุ่งมั่น', 5, 2, 'supervisor'),
('1100100000010', 1000520001, 4, 'จารุวรรณ', 'ขยันเรียน', 5, 2, 'supervisor'),
('1234567891234', 1000520001, 223, 'แอดมิน', 'ทำงานแล้วค่ะ', 7, 2, 'admin');

-- --------------------------------------------------------

--
-- Table structure for table `teacher`
--

CREATE TABLE `teacher` (
  `office_id` int(11) NOT NULL COMMENT 'รหัสสังกัด',
  `school_id` int(11) NOT NULL COMMENT 'รหัสโรงเรียน',
  `t_pid` varchar(13) NOT NULL COMMENT 'รหัสบัตร ปชช. ครู',
  `prefix_id` int(11) NOT NULL COMMENT 'รหัสคำนำหน้า',
  `f_name` varchar(100) DEFAULT NULL COMMENT 'ชื่อ',
  `m_name` varchar(100) DEFAULT NULL COMMENT 'ชื่อกลาง',
  `l_name` varchar(100) DEFAULT NULL COMMENT 'นามสกุล',
  `subjectgroup_id` int(11) NOT NULL COMMENT 'รหัสกลุ่มสาระฯ',
  `position_id` int(11) NOT NULL COMMENT 'รหัสตำแหน่ง',
  `rank_id` int(11) NOT NULL COMMENT 'รหัสวิทยฐานะ'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `teacher`
--

INSERT INTO `teacher` (`office_id`, `school_id`, `t_pid`, `prefix_id`, `f_name`, `m_name`, `l_name`, `subjectgroup_id`, `position_id`, `rank_id`) VALUES
(1000520001, 1052500514, '3100100000001', 3, 'กิตติศักดิ์', NULL, 'เรียนดี', 1, 1, 1),
(1000520001, 1052500514, '3100100000002', 4, 'กาญจนา', NULL, 'พาเพลิน', 2, 1, 2),
(1000520001, 1052500513, '3100100000003', 3, 'จตุรนต์', NULL, 'ขยันคิด', 3, 1, 3),
(1000520001, 1052500516, '3100100000004', 4, 'ชลทิชา', NULL, 'สายน้ำ', 4, 2, 5),
(1000520001, 1052500517, '3100100000005', 3, 'ณัฐพล', NULL, 'เก่งกล้า', 5, 1, 1),
(1000520001, 1052500518, '3100100000006', 4, 'ดวงพร', NULL, 'สดใส', 6, 1, 2),
(1000520001, 1052500519, '3100100000007', 3, 'ทรงพล', NULL, 'แข็งแรง', 7, 1, 3),
(1000520001, 1052500520, '3100100000008', 4, 'นันทนา', NULL, 'ใจดี', 8, 2, 5),
(1000520001, 1052500513, '3100100000009', 3, 'ปิยะพงษ์', NULL, 'ยอดเยี่ยม', 1, 1, 1),
(1000520001, 1052500514, '3100100000010', 4, 'พรทิพย์', NULL, 'ยิ้มหวาน', 2, 1, 2),
(1000520001, 1052500517, '3100100000011', 3, 'รณชัย', NULL, 'มุ่งมั่น', 3, 1, 3),
(1000520001, 1052500516, '3100100000012', 4, 'ศิริพร', NULL, 'งดงาม', 4, 2, 5),
(1000520001, 1052500520, '3100100000013', 3, 'อภิสิทธิ์', NULL, 'รักเรียน', 5, 1, 1),
(1000520001, 1052500518, '3100100000014', 4, 'เบญจมาศ', NULL, 'บานแย้ม', 6, 1, 2),
(1000520001, 1052500514, '3100100000015', 3, 'พงศธร', NULL, 'สอนเก่ง', 7, 1, 3),
(1000520001, 1052500513, '3100100000016', 4, 'สุมิตรา', NULL, 'พาชื่น', 8, 2, 5),
(1000520001, 1052500517, '3100100000017', 3, 'ไพโรจน์', NULL, 'รุ่งเรือง', 1, 1, 1),
(1000520001, 1052500516, '3100100000018', 4, 'กุลธิดา', NULL, 'ฟ้าใส', 2, 1, 2),
(1000520001, 1052500520, '3100100000019', 3, 'วรวุฒิ', NULL, 'กล้าหาญ', 3, 1, 3),
(1000520001, 1052500518, '3100100000020', 4, 'มลฤดี', NULL, 'มีสุข', 4, 2, 5),
(1000520001, 1051510296, '3100100000021', 3, 'ธนพล', NULL, 'คนดี', 5, 1, 1),
(1000520001, 1051510297, '3100100000022', 4, 'โสภา', NULL, 'น่ารัก', 6, 1, 2),
(1000520001, 1051510298, '3100100000023', 3, 'ทวีศักดิ์', NULL, 'คงมั่น', 7, 1, 3),
(1000520001, 1051510299, '3100100000024', 4, 'อุมาพร', NULL, 'สอนดี', 8, 2, 5),
(1000520001, 1051510300, '3100100000025', 3, 'ปกรณ์', NULL, 'รอบรู้', 1, 1, 1),
(1000520001, 1051510301, '3100100000026', 4, 'วิไลลักษณ์', NULL, 'เลิศล้ำ', 2, 1, 2),
(1000520001, 1051510302, '3100100000027', 3, 'มานะ', NULL, 'อดทน', 3, 1, 3),
(1000520001, 1051510304, '3100100000028', 4, 'พัชราภา', NULL, 'สวยสม', 4, 2, 5),
(1000520001, 1051510305, '3100100000029', 3, 'สมบัติ', NULL, 'มีเงิน', 5, 1, 1),
(1000520001, 1051510306, '3100100000030', 4, 'เรณู', NULL, 'นุ่มนวล', 6, 1, 2),
(1000520001, 1051510307, '3100100000031', 3, 'อานนท์', NULL, 'พ้นภัย', 7, 1, 3),
(1000520001, 1051510309, '3100100000032', 4, 'ขวัญใจ', NULL, 'ใจรัก', 8, 2, 5),
(1000520001, 1052500514, '3100100000033', 3, 'สุรศักดิ์', NULL, 'ยิ่งใหญ่', 1, 1, 1),
(1000520001, 1052500513, '3100100000034', 4, 'จินตนา', NULL, 'ท่าสวย', 2, 1, 2),
(1000520001, 1052500517, '3100100000035', 3, 'ธวัชชัย', NULL, 'ชนะศึก', 3, 1, 3),
(1000520001, 1052500516, '3100100000036', 4, 'สุพรรษา', NULL, 'หน้าใส', 4, 2, 5),
(1000520001, 1052500520, '3100100000037', 3, 'สิทธิชัย', NULL, 'ใจสู้', 5, 1, 1),
(1000520001, 1052500518, '3100100000038', 4, 'นวรัตน์', NULL, 'รุ่งอรุณ', 6, 1, 2),
(1000520001, 1052500514, '3100100000039', 3, 'เก่งกาจ', NULL, 'ปราดเปรื่อง', 7, 1, 3),
(1000520001, 1052500513, '3100100000040', 4, 'อลิสา', NULL, 'พาฝัน', 8, 2, 5),
(1000520001, 1052500517, '3100100000041', 3, 'บุญส่ง', NULL, 'มงคล', 1, 1, 1),
(1000520001, 1052500516, '3100100000042', 4, 'สายใจ', NULL, 'ใฝ่เรียน', 2, 1, 2),
(1000520001, 1052500520, '3100100000043', 3, 'จักรพงษ์', NULL, 'ยงยืน', 3, 1, 3),
(1000520001, 1052500518, '3100100000044', 4, 'สิริมา', NULL, 'น่าชม', 4, 2, 5),
(1000520001, 1052500514, '3100100000045', 3, 'เจษฎา', NULL, 'มุ่งเจริญ', 5, 1, 1),
(1000520001, 1052500513, '3100100000046', 4, 'เพ็ญศรี', NULL, 'มีชัย', 6, 1, 2),
(1000520001, 1052500517, '3100100000047', 3, 'อนุสรณ์', NULL, 'สอนเก่ง', 7, 1, 3),
(1000520001, 1052500516, '3100100000048', 4, 'ดาริกา', NULL, 'ตาหวาน', 8, 2, 5),
(1000520001, 1052500520, '3100100000049', 3, 'ไพศาล', NULL, 'รักสงบ', 1, 1, 1),
(1000520001, 1052500518, '3100100000050', 4, 'กานต์พิชชา', NULL, 'พัฒนา', 2, 1, 2);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `certificate_log`
--
ALTER TABLE `certificate_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_cert_to_teacher_v2` (`t_pid`),
  ADD KEY `fk_cert_to_supervisor_v2` (`p_id`);

--
-- Indexes for table `images`
--
ALTER TABLE `images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_images_to_teacher` (`t_pid`),
  ADD KEY `fk_images_to_supervisor` (`p_id`);

--
-- Indexes for table `kpi_answers`
--
ALTER TABLE `kpi_answers`
  ADD PRIMARY KEY (`question_id`,`t_pid`,`subject_code`,`inspection_time`,`academic_year`),
  ADD KEY `fk_kpi_ans_teacher` (`t_pid`),
  ADD KEY `fk_kpi_ans_supervisor` (`p_id`);

--
-- Indexes for table `kpi_indicators`
--
ALTER TABLE `kpi_indicators`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `kpi_indicator_suggestions`
--
ALTER TABLE `kpi_indicator_suggestions`
  ADD PRIMARY KEY (`indicator_id`,`t_pid`,`subject_code`,`inspection_time`,`academic_year`),
  ADD KEY `fk_sugg_to_teacher` (`t_pid`),
  ADD KEY `fk_sugg_to_supervisor` (`p_id`);

--
-- Indexes for table `kpi_questions`
--
ALTER TABLE `kpi_questions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_kpi_question_indicator` (`indicator_id`);

--
-- Indexes for table `office`
--
ALTER TABLE `office`
  ADD PRIMARY KEY (`office_id`);

--
-- Indexes for table `position`
--
ALTER TABLE `position`
  ADD PRIMARY KEY (`position_id`);

--
-- Indexes for table `prefix`
--
ALTER TABLE `prefix`
  ADD PRIMARY KEY (`prefix_id`);

--
-- Indexes for table `quickwin_options`
--
ALTER TABLE `quickwin_options`
  ADD PRIMARY KEY (`OptionID`);

--
-- Indexes for table `quickwin_satisfaction_answers`
--
ALTER TABLE `quickwin_satisfaction_answers`
  ADD PRIMARY KEY (`t_pid`,`academic_year`,`question_id`);

--
-- Indexes for table `quick_win`
--
ALTER TABLE `quick_win`
  ADD PRIMARY KEY (`t_pid`,`academic_year`),
  ADD KEY `fk_quickwin_supervisor` (`p_id`);

--
-- Indexes for table `ranks`
--
ALTER TABLE `ranks`
  ADD PRIMARY KEY (`rank_id`);

--
-- Indexes for table `satisfaction_answers`
--
ALTER TABLE `satisfaction_answers`
  ADD PRIMARY KEY (`t_pid`,`subject_code`,`inspection_time`,`academic_year`,`question_id`);

--
-- Indexes for table `satisfaction_questions`
--
ALTER TABLE `satisfaction_questions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `school`
--
ALTER TABLE `school`
  ADD PRIMARY KEY (`school_id`);

--
-- Indexes for table `site_views`
--
ALTER TABLE `site_views`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `subject_group`
--
ALTER TABLE `subject_group`
  ADD PRIMARY KEY (`subjectgroup_id`);

--
-- Indexes for table `supervision_sessions`
--
ALTER TABLE `supervision_sessions`
  ADD PRIMARY KEY (`t_pid`,`subject_code`,`inspection_time`,`academic_year`),
  ADD KEY `fk_session_supervisor` (`p_id`);

--
-- Indexes for table `supervisor`
--
ALTER TABLE `supervisor`
  ADD PRIMARY KEY (`p_id`),
  ADD KEY `fk_sup_office` (`office_id`),
  ADD KEY `fk_sup_prefix` (`prefix_id`),
  ADD KEY `fk_sup_position` (`position_id`),
  ADD KEY `fk_sup_rank` (`rank_id`);

--
-- Indexes for table `teacher`
--
ALTER TABLE `teacher`
  ADD PRIMARY KEY (`t_pid`),
  ADD KEY `res_teacher_office` (`office_id`),
  ADD KEY `res_teacher_school` (`school_id`),
  ADD KEY `res_teacher_prefix` (`prefix_id`),
  ADD KEY `res_teacher_position` (`position_id`),
  ADD KEY `res_teacher_rank` (`rank_id`),
  ADD KEY `res_teacher_group` (`subjectgroup_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `certificate_log`
--
ALTER TABLE `certificate_log`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'รหัสใบเกียรติบัตร', AUTO_INCREMENT=57;

--
-- AUTO_INCREMENT for table `images`
--
ALTER TABLE `images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'รหัสรูปภาพ', AUTO_INCREMENT=126;

--
-- AUTO_INCREMENT for table `kpi_questions`
--
ALTER TABLE `kpi_questions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `certificate_log`
--
ALTER TABLE `certificate_log`
  ADD CONSTRAINT `fk_cert_to_supervisor_v2` FOREIGN KEY (`p_id`) REFERENCES `supervisor` (`p_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_cert_to_teacher_v2` FOREIGN KEY (`t_pid`) REFERENCES `teacher` (`t_pid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `images`
--
ALTER TABLE `images`
  ADD CONSTRAINT `fk_images_to_supervisor` FOREIGN KEY (`p_id`) REFERENCES `supervisor` (`p_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_images_to_teacher` FOREIGN KEY (`t_pid`) REFERENCES `teacher` (`t_pid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `kpi_answers`
--
ALTER TABLE `kpi_answers`
  ADD CONSTRAINT `fk_kpi_ans_question` FOREIGN KEY (`question_id`) REFERENCES `kpi_questions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_kpi_ans_supervisor` FOREIGN KEY (`p_id`) REFERENCES `supervisor` (`p_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_kpi_ans_teacher` FOREIGN KEY (`t_pid`) REFERENCES `teacher` (`t_pid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `kpi_indicator_suggestions`
--
ALTER TABLE `kpi_indicator_suggestions`
  ADD CONSTRAINT `fk_ind_sugg_to_indicator` FOREIGN KEY (`indicator_id`) REFERENCES `kpi_indicators` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_sugg_to_supervisor` FOREIGN KEY (`p_id`) REFERENCES `supervisor` (`p_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_sugg_to_teacher` FOREIGN KEY (`t_pid`) REFERENCES `teacher` (`t_pid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `kpi_questions`
--
ALTER TABLE `kpi_questions`
  ADD CONSTRAINT `fk_kpi_question_indicator` FOREIGN KEY (`indicator_id`) REFERENCES `kpi_indicators` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `quickwin_satisfaction_answers`
--
ALTER TABLE `quickwin_satisfaction_answers`
  ADD CONSTRAINT `fk_qw_answers_to_quickwin` FOREIGN KEY (`t_pid`,`academic_year`) REFERENCES `quick_win` (`t_pid`, `academic_year`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `quick_win`
--
ALTER TABLE `quick_win`
  ADD CONSTRAINT `fk_quickwin_supervisor` FOREIGN KEY (`p_id`) REFERENCES `supervisor` (`p_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_quickwin_teacher` FOREIGN KEY (`t_pid`) REFERENCES `teacher` (`t_pid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `supervision_sessions`
--
ALTER TABLE `supervision_sessions`
  ADD CONSTRAINT `fk_session_supervisor` FOREIGN KEY (`p_id`) REFERENCES `supervisor` (`p_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_session_teacher` FOREIGN KEY (`t_pid`) REFERENCES `teacher` (`t_pid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `supervisor`
--
ALTER TABLE `supervisor`
  ADD CONSTRAINT `fk_sup_office` FOREIGN KEY (`office_id`) REFERENCES `office` (`office_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_sup_position` FOREIGN KEY (`position_id`) REFERENCES `position` (`position_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_sup_prefix` FOREIGN KEY (`prefix_id`) REFERENCES `prefix` (`prefix_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_sup_rank` FOREIGN KEY (`rank_id`) REFERENCES `ranks` (`rank_id`) ON UPDATE CASCADE;

--
-- Constraints for table `teacher`
--
ALTER TABLE `teacher`
  ADD CONSTRAINT `res_teacher_group` FOREIGN KEY (`subjectgroup_id`) REFERENCES `subject_group` (`subjectgroup_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `res_teacher_office` FOREIGN KEY (`office_id`) REFERENCES `office` (`office_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `res_teacher_position` FOREIGN KEY (`position_id`) REFERENCES `position` (`position_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `res_teacher_prefix` FOREIGN KEY (`prefix_id`) REFERENCES `prefix` (`prefix_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `res_teacher_rank` FOREIGN KEY (`rank_id`) REFERENCES `ranks` (`rank_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `res_teacher_school` FOREIGN KEY (`school_id`) REFERENCES `school` (`school_id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
