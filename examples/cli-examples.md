# OpenTimestamps CLI Examples

## Basic Usage

```bash
# Create a timestamp
ots stamp document.pdf

# Verify a timestamp
ots verify document.pdf.ots document.pdf

# Upgrade pending attestations
ots upgrade document.pdf.ots

# Get timestamp information
ots info document.pdf.ots

# Tree view
ots info --tree document.pdf.ots

# Compact view  
ots info --compact document.pdf.ots

# JSON output
ots info --json document.pdf.ots

# Create attached timestamp (embedded in file)
ots stamp --attached document.pdf

# Create timestamp with custom output file
ots stamp -o myfile.timestamp document.pdf

# Wait for attestations to be confirmed
ots stamp --wait document.pdf

# Use specific calendar servers
ots stamp --calendar https://a.pool.opentimestamps.org,https://b.pool.opentimestamps.org document.pdf

# Verify with verbose output
ots verify -v document.pdf.ots document.pdf

# Verify and output JSON
ots verify -j document.pdf.ots document.pdf

# Force upgrade even if no upgrades available
ots upgrade --force document.pdf.ots

# Show detailed timestamp information
ots info -v document.pdf.ots

# Check system status
ots status

# Check calendar server status
ots server status

# List available calendar servers
ots server list