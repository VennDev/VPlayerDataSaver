# VPlayerDataSaver
- A simple plugin PMMP 5 that logs the player's data to a database or YAML file.

# Virions
- [LibVapmPMMP](https://github.com/VennDev/LibVapmPMMP)
- [VapmDatabasePMMP](https://github.com/VennDev/VapmDatabase)

# Config
```yml
---
database:
  host: localhost
  port: 3306
  database: vplayerdatasaver
  table-name: vplayerdatasaver
  username: root
  password: ''
  type: yaml # mysql or sqlite or yaml

  # SQL queries to create the table or more...
  # The default data is saved in the player's 2 columns xuid and name
  # If you want to add more data, you can add it here. Use the following format:
  # 'ALTER TABLE `vmskyblock` ADD COLUMN `column_name` TEXT NOT NULL DEFAULT ""'
  # This requires a bit of understanding of querying and processing the underlying columns in the database
  addition-sql-queries: []

  # Data addition to the player's data if YAML is used
  # The default data that is saved in the player's 2 columns xuid and name
  # If you want to add more data, you can add it here. Use the following format:
  # 'column_name: value'
  addition-yaml-data: []
...
```

# Credits
- API Designer: [VennDev](https://github.com/VennDev)
- Paypal: pnam5005@gmail.com
