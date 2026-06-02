/* Poolhall UI kit — Contact page */

function Contact({ go }) {
  const [sent, setSent] = useState(false);
  const [busy, setBusy] = useState(false);
  const submit = e => { e.preventDefault(); setBusy(true); setTimeout(() => { setBusy(false); setSent(true); }, 1200); };
  return (
    <main className="bg-paper">
      {/* header band */}
      <section className="bg-navy" style={{ padding: "52px 0 56px" }}>
        <div className="container">
          <p className="eyebrow on-dark">Get in touch</p>
          <h1 className="h-display on-dark" style={{ fontSize: "clamp(2rem,3.5vw,2.9rem)", maxWidth: "16ch" }}>We'd love to<br />hear from you</h1>
          <p className="lead on-dark" style={{ marginTop: 14, maxWidth: "52ch" }}>Whether you're after your next role or looking to hire, drop us a line and we'll reply within one working day.</p>
        </div>
      </section>

      <div className="container" style={{ padding: "56px 24px 84px" }}>
        <div className="contact-layout">
          {/* details */}
          <aside>
            <div className="contact-block">
              <span className="contact-ic"><Icon name="phone" /></span>
              <div><div className="cb-label">Call us</div><a className="cb-value" href="tel:01215163000">0121 516 3000</a></div>
            </div>
            <div className="contact-block">
              <span className="contact-ic"><Icon name="mail" /></span>
              <div><div className="cb-label">Email</div><a className="cb-value" href="mailto:jobs@poolhallrecruitment.co.uk">jobs@poolhallrecruitment.co.uk</a></div>
            </div>
            <div className="contact-block">
              <span className="contact-ic"><Icon name="map-pin" /></span>
              <div><div className="cb-label">Visit</div><span className="cb-value">Grosvenor House, 11 St Pauls Square,<br />Birmingham, B3 1RB</span></div>
            </div>
            <div className="contact-block">
              <span className="contact-ic"><Icon name="clock" /></span>
              <div><div className="cb-label">Opening hours</div><span className="cb-value">Mon–Fri · 8:30am – 5:30pm</span></div>
            </div>
            <div className="contact-map" role="img" aria-label="Map of St Pauls Square, Birmingham">
              <Icon name="map-pin" />
              <span>St Pauls Square, Birmingham</span>
            </div>
          </aside>

          {/* form */}
          <div className="form-card">
            {sent ? (
              <div style={{ textAlign: "center", padding: "30px 0" }}>
                <div style={{ width: 60, height: 60, borderRadius: "50%", background: "var(--success-50)", color: "var(--success-600)", display: "flex", alignItems: "center", justifyContent: "center", margin: "0 auto 18px" }}><Icon name="check" style={{ width: 28, height: 28 }} /></div>
                <h2 className="h3">Message sent</h2>
                <p className="ph-body" style={{ marginTop: 10 }}>Thanks for getting in touch. One of the team will reply within one working day.</p>
                <Button variant="ghost" style={{ marginTop: 22 }} onClick={() => go("home")}>Back to home</Button>
              </div>
            ) : (
              <form onSubmit={submit}>
                <h2 className="h3" style={{ marginBottom: 6 }}>Send us a message</h2>
                <p className="ph-meta" style={{ marginBottom: 20 }}>Fields marked <span style={{ color: "var(--orange-600)" }}>*</span> are required.</p>
                <div className="form-row">
                  <div className="fieldset"><label>Name <span className="req">*</span></label><input className="input" required placeholder="Jane Smith" /></div>
                  <div className="fieldset"><label>Email <span className="req">*</span></label><input className="input" type="email" required placeholder="jane@email.com" /></div>
                </div>
                <div className="form-row">
                  <div className="fieldset"><label>Phone</label><input className="input" placeholder="07700 900000" /></div>
                  <div className="fieldset">
                    <label>I'm a… <span className="req">*</span></label>
                    <select className="input" required defaultValue="">
                      <option value="" disabled>Please choose</option>
                      <option>Candidate looking for work</option>
                      <option>Employer looking to hire</option>
                      <option>Recruiter interested in joining</option>
                      <option>Something else</option>
                    </select>
                  </div>
                </div>
                <div className="fieldset"><label>Message <span className="req">*</span></label><textarea className="input" required placeholder="How can we help?"></textarea></div>
                <label className="consent" style={{ marginBottom: 22 }}>
                  <input type="checkbox" required />
                  <span>I consent to Poolhall Recruitment storing my details to respond to this enquiry, per the <a href="#" style={{ color: "var(--link)", fontWeight: 600 }}>Privacy Policy</a>. <span className="req" style={{ color: "var(--orange-600)" }}>*</span></span>
                </label>
                <Button variant="primary" size="lg" className="btn-block" iconRight="arrow-right" busy={busy} type="submit">{busy ? "Sending…" : "Send message"}</Button>
              </form>
            )}
          </div>
        </div>
      </div>
    </main>
  );
}

window.Contact = Contact;
