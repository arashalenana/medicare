# Family Medicare Clinic & Laboratory Clinical Management System

## Project Overview

**Family Medicare** is a comprehensive Point-of-Sale (POS) system designed specifically for clinics and laboratories. It manages consultations, laboratory tests, prescriptions, medicines inventory, billing, and comprehensive reporting.

### Key Features
- **Consultation Management**: Track patient consultations with doctors
- **Laboratory Module**: Manage lab tests and results
- **Prescription Management**: Create and track prescriptions with detailed items
- **Inventory Management**: Track medicines with expiry alerts
- **Enhanced Billing System**: Separate billing categories for consultations, medications, lab fees, and drug sales
- **Comprehensive Reporting**: Revenue, patient, inventory, and service reports
- **Role-Based Access Control**: Admin, Receptionist, and Cashier roles with specific permissions

---

## Database Schema

### Core Tables

#### Users Table
- Stores system users with roles: Admin, Receptionist, Cashier
- Tracks user credentials and contact information

#### Patients Table
- Replaces the old "Customers" table
- Stores patient information including demographics, ID number, and insurance details
- Supports patient history and follow-up tracking

#### Medicines Table
- Manages pharmacy inventory
- Tracks quantity, pricing, expiry dates, and batch numbers
- Includes manufacturer and dosage information

#### Consultations Table
- Records patient consultations with doctors
- Tracks consultation date, reason, diagnosis, and status
- Links to patients and consultation charges

#### Consultation Charges Table
- Manages consultation fees
- Allows multiple charges per consultation

#### Prescriptions Table
- Stores prescription records
- Links to patients and consultations
- Tracks prescription status (Active, Completed, Cancelled)

#### Prescription Items Table
- Details individual medicines in a prescription
- Includes dosage, frequency, duration, and instructions

#### Lab Tests Table
- Manages laboratory test orders
- Tracks test date, category, and charges
- Links to patients and consultations

#### Lab Test Results Table
- Stores test results with values, units, and reference ranges
- Includes result status (Normal, Abnormal, Critical)

#### Sales Table
- Enhanced billing with separate categories:
  - Consultation Fee
  - Medication Fee
  - Lab Fee
  - Drug Sales
- Tracks payment method and status

#### Sale Items Table
- Details individual items in a sale
- Supports multiple item types (Medicine, Consultation, Lab Test)

---

## Role-Based Access Control (RBAC)
-Every user has their own role


