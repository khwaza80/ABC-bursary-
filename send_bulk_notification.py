import sys
import smtplib
from email.mime.text import MIMEText

def send_email(recipient, subject, message):
    sender = "nkanyisokhwaza2@gmail.com"
    password = "ludi fdhk zimz swmc"  # Updated App Password
    try:
        server = smtplib.SMTP("smtp.gmail.com", 587)
        server.starttls()
        server.login(sender, password)
        msg = MIMEText(message, "html")
        msg['Subject'] = subject
        msg['From'] = sender
        msg['To'] = recipient
        server.sendmail(sender, recipient, msg.as_string())
        server.quit()
        print("Email sent successfully to", recipient)
    except Exception as e:
        print("Error sending email:", str(e))

if __name__ == "__main__":
    if len(sys.argv) != 4:
        print("Usage: python send_bulk_notifications.py recipient_email subject message")
        sys.exit(1)
    recipient = sys.argv[1]
    subject = sys.argv[2]
    message = sys.argv[3]
    send_email(recipient, subject, message)