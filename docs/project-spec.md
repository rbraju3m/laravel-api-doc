# API Documentation Generator - Project Specification

## Goal
Automatically generate API documentation by analyzing a Laravel project.

## Features

1. Scan Laravel route files
2. Extract API endpoints
3. Detect controller methods
4. Read FormRequest validation rules
5. Generate request parameter documentation
6. Generate example JSON responses
7. Provide a web UI to browse API docs

## Stack

### Backend
- Laravel 12
- PHP 8.2+

### Frontend
- React 19
- Inertia.js
- Mantine UI

## Requirements

- Use Laravel best practices
- Use service classes
- Build a RouteScanner service
- Build a ControllerParser
- Build a RequestValidationParser
- Build a DocGenerator
- Create a /docs/api page

## Deliverables

1. Project architecture
2. Service classes
3. Database schema
4. Controllers
5. Routes
6. React UI
