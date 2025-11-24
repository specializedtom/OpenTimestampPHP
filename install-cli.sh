#!/bin/bash
# install-cli.sh - Install OpenTimestamps CLI

echo "Installing OpenTimestamps CLI..."

# Check if Composer is available
if ! command -v composer &> /dev/null; then
    echo "Error: Composer is required but not installed."
    echo "Please install Composer from https://getcomposer.org/"
    exit 1
fi

# Install dependencies
echo "Installing dependencies..."
composer install

# Make the CLI executable
chmod +x bin/ots

# Create symlink to /usr/local/bin if requested
if [ "$1" = "--global" ]; then
    if [ -w /usr/local/bin ]; then
        ln -sf "$(pwd)/bin/ots" /usr/local/bin/ots
        echo "Created global symlink: /usr/local/bin/ots"
    else
        echo "Note: Cannot create global symlink without write permission to /usr/local/bin"
        echo "You can create it manually: sudo ln -s $(pwd)/bin/ots /usr/local/bin/ots"
    fi
fi

echo ""
echo "OpenTimestamps CLI installed successfully!"
echo ""
echo "Usage:"
echo "  ./bin/ots stamp document.pdf          # Create a timestamp"
echo "  ./bin/ots verify document.pdf.ots     # Verify a timestamp"
echo "  ./bin/ots help                        # Show help"
echo ""
echo "For global installation, run: ./install-cli.sh --global"