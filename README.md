# AI Ticket Classifier

An intelligent system for automatic classification of support tickets using artificial intelligence and ITIL methodology.

## Overview

This API automatically categorizes and prioritizes support tickets based on their content, using advanced AI models with cascading fallback for high availability. The system applies ITIL matrix calculations to determine priority levels and SLA deadlines.

## Features

- Automatic Classification: Categorizes tickets into Technical, Commercial, Billing, Support, or General
- Priority Calculation: Uses ITIL matrix (Impact × Urgency) to determine Critical, High, Medium, or Low priority
- Sentiment Analysis: Analyzes ticket content for Positive, Negative, or Neutral sentiment
- SLA Management: Automatically calculates SLA deadlines based on priority levels
- Security: HMAC signatures, nonce anti-replay protection, and rate limiting
- CSV Processing: Upload and process multiple tickets via CSV files
- Result Storage: Persistent storage of classification results

## Quick Start

1. Generate a CSV template with example tickets
2. Upload your ticket data for classification
3. Retrieve classification results with detailed reasoning

## API Endpoints

- Generate CSV Template: Creates a signed CSV template with sample data
- Upload Tickets: Processes and classifies uploaded ticket data
- Get Results: Retrieves classification results for specific jobs
- Health Check: Monitors system status and service availability

## Technology Stack

- Laravel 12 (Backend API)
- Next.js 14 (Frontend Interface)
- PostgreSQL (Database)
- OpenRouter AI Models (Classification Engine)