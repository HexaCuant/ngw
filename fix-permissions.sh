#!/bin/bash
# Fix permissions for NGW on EndeavourOS/Arch Linux
# Web server user: http
# Development group: web (allows your user to edit files)

echo "Fixing NGW permissions..."
echo "========================="
echo ""

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check if running with appropriate privileges
if [ ! -w "data/" ]; then
    echo -e "${YELLOW}Note: You may need sudo for some operations${NC}"
    echo ""
fi

# Fix ownership: http:web
echo "Setting ownership to http:web..."
sudo chown -R http:web data/
sudo chown http:web data/ngw.db

# Fix permissions for database directory
echo "Setting directory permissions..."
sudo chmod 775 data/

# Fix permissions for database file
echo "Setting database file permissions..."
sudo chmod 664 data/ngw.db

# Verify the changes
echo ""
echo -e "${GREEN}✓ Permissions fixed!${NC}"
echo ""
echo "Current permissions:"
ls -lh data/

echo ""
echo "Permissions summary:"
echo "  data/        775 (rwxrwxr-x) - http:web can read/write, others can read"
echo "  data/ngw.db  664 (rw-rw-r--) - http:web can read/write, others can read"
echo ""
echo "This allows:"
echo "  • Web server (http) to read/write the database"
echo "  • Your user (in 'web' group) to edit and backup the database"
echo "  • Other users to read (but not modify)"
