# ⭐ TNX API ⭐

## 🧭 OVERVIEW
TNXAPI2 is a PHP based API and management platform that connects databases, emails and other business data. It provides endpoints for automation as well as a simple web interface so that users can manage their accounts, workflows and documents.

## 📂 CODE STRUCTURE
- **API/** – public endpoints such as `nexus.php` for remote access
- **BRAIN/** – workflow engine handling the main business logic
- **UI/** – HTML/PHP front end for user interaction
- **SETUP/** – scripts for preparing database tables and initial configuration
- **SHOP/** – optional modules for shop integrations
- **HELP/** – static help pages and documentation snippets
- **UPLOADS/** – default storage for user provided files
- **config.php** – configuration file for database access and API keys

## 📖 CONCEPT

### ✨ VISION
> **Do more of what you love** while our AI system handles the administrative stuff!  
We connect AI with your business data to create your most powerful administrative employee.

---

### 🛠️ HOW IT WORKS
- **Interaction Tools**  
  - 💬 AI Chat – talk with your data as you would with a colleague  
  - 🟦 Entry Edit – edit entries directly using smart forms  
  - ✉️ Emailbot – let others ask your data questions via email  
  - 🌐 Shop – share datasets and accept orders  

- **Main System**  
  - 🧠 AI Brain – structures, visualizes, modifies and translates between humans and machines  
  - 🕸️ API Nexus – checks and prepares AI code for execution  
  - 🕳️ Stargate – connects TNX API with your server  

- **Your Server**  
  - 🧬 Your Data – databases and files remain on your own server  
  - 🔧 Other Tools – extend with any additional custom code  
  - 🕳️ Stargate – executes code on your server and returns responses  

---

### 🌱 EVERYTHING BECOMES POSSIBLE
Unlike many rigid business software tools, TNX API lets you run everything on your **own dedicated server**.  
You can change, modify and adjust everything to your specific needs and add **every custom solution imaginable**.

---

### 🤝 WE ARE OPEN SOURCE
As you can see   XD, bringing basically the same advantages as above, but now even better.

---

### 🔧 ALL COMMON DATABASE ACTIONS
`INSERT INTO`, `UPDATE`, `DELETE`, `SELECT`, **SEARCH**, **GET CONTEXT**  
➡ getting your data ready for **AI**!

---

### 🛡️ SECURITY CONSTANTLY IN MIND
- data stays on your own dedicated server (even legacy servers supported)  
- automatic log files for every AI action  
- regular backups on your server possible  
- different access rights for admin and user accounts  

---

### 💡 EXAMPLES OF WHAT OUR SYSTEM CAN DO
- create charts and editable tables for data visualization  
- write PDFs *(invoices, offers, delivery receipts, reports, contracts, legal documents, …)*  
- handle uploads *(extract key information, classify and enter into database)*  
- code like an expert *(adjust entries or bulk update tables in seconds)*  

---

### 💬 OUR PROMISE
> *"We take care of your organizational business stuff, so you can concentrate on building great products and services for great people."*

## 🧪 TESTING SETUP
For local testing you must fill in the placeholders in `config.php` with your own credentials. Replace every `PLACEHOLDER_*` value with a real database server, name, user, password and API key before running the code.

## 🔗 LINKS AND CONTACT
landing page: www.tnxapi.com/UI/LandingPage.php
to join the onboarding list, email to: hi@tnxapi.com







<br><br><br><br><br><br><br><br><br><br>
## 💡 FUTURE IDEAS AND GENERAL NOTES
ADD SOMETHING TO:
1.) have automatically recurring calls to the AI
and
2.) have automatically recurring calls to nexus.php directly (direct code execution)
(for example to know, who is late with their payments)

- improve SUPPORT BOT (in general, more testing and also allow to add entries to car directly if price flag is set at least once)
- upgrade to more powerful models again
- improve SetupDatabases.php
- ? add formbuilder and forms
- add possibility to save the PDFs created (and maybe also tables and charts)
- add possibility to upload, change and delete attachments to databases tables entries directly over the chat too
- add database action restriction for team members (restrict tables and actions also as plain text which is added per employee, for example "DON'T CHANGE customerDB, for transactionDB only INSERT is allowed, but also suppress the corresponding label options and even whole tables (if this user isn't allowed to do anything with it or access it using the currently selected action and do this for both the AI and the direct accessing using ./UI/entry.php)
