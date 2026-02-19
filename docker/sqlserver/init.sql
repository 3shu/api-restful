-- SQL Server initialization script
-- Database for Books microservice

CREATE DATABASE books_db;
GO

USE books_db;
GO

-- Books table will be created by Doctrine migrations
-- This file is ready for any additional initialization needed

SELECT 'SQL Server Books Database Initialized' AS Status;
GO
