/* Poolhall UI kit v2 — Apply (validated form, page + modal) + Register */

/* shared validated application form ------------------------------------- */
function ApplyForm({ job, onDone, compact }) {
  const [f, setF] = useState({ first: "", last: "", email: "", phone: "", msg: "", consent: false });
  const [file, setFile] = useState(null);
  const [errs, setErrs] = useState({});
  const [touched, setTouched] = useState({});
  const set = (k, v) => setF(s => ({ ...s, [k]: v }));

  const validate = () => {
    const e = {};
    if (!f.first.trim()) e.first = "Enter your first name";
    if (!f.last.trim()) e.last = "Enter your last name";
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(f.email)) e.email = "Enter a valid email address";
    if (!f.phone.trim()) e.phone = "Enter a contact number";
    if (!file) e.file = "Attach your CV (PDF or Word, under 5 MB)";
    if (!f.consent) e.consent = "Please tick to continue";
    return e;
  };
  const submit = ev => {
    ev.preventDefault();
    const e = validate();
    setErrs(e); setTouched({ first: 1, last: 1, email: 1, phone: 1, file: 1, consent: 1 });
    if (Object.keys(e).length === 0) onDone(f.first);
  };
  const blur = k => { setTouched(t => ({ ...t, [k]: 1 })); setErrs(validate()); };
  const showErr = k => touched[k] && errs[k];
  const onFile = e => {
    const file0 = e.target.files[0];
    if (file0 && file0.size > 5 * 1024 * 1024) { setErrs(x => ({ ...x, file: "That file is over 5 MB" })); setTouched(t => ({ ...t, file: 1 })); return; }
    setFile(file0 ? file0.name : null);
    setErrs(x => { const n = { ...x }; delete n.file; return n; });
  };

  return (
    <form onSubmit={submit} noValidate>
      <div className="row2">
        <div className="field">
          <label>First name <span className="req">*</span></label>
          <input className={"input" + (showErr("first") ? " bad" : "")} value={f.first} onChange={e => set("first", e.target.value)} onBlur={() => blur("first")} placeholder="Jane" />
          {showErr("first") && <div className="field-err">{errs.first}</div>}
        </div>
        <div className="field">
          <label>Last name <span className="req">*</span></label>
          <input className={"input" + (showErr("last") ? " bad" : "")} value={f.last} onChange={e => set("last", e.target.value)} onBlur={() => blur("last")} placeholder="Smith" />
          {showErr("last") && <div className="field-err">{errs.last}</div>}
        </div>
      </div>
      <div className="row2">
        <div className="field">
          <label>Email <span className="req">*</span></label>
          <input className={"input" + (showErr("email") ? " bad" : "")} type="email" value={f.email} onChange={e => set("email", e.target.value)} onBlur={() => blur("email")} placeholder="jane@email.com" />
          {showErr("email") && <div className="field-err">{errs.email}</div>}
        </div>
        <div className="field">
          <label>Phone <span className="req">*</span></label>
          <input className={"input" + (showErr("phone") ? " bad" : "")} value={f.phone} onChange={e => set("phone", e.target.value)} onBlur={() => blur("phone")} placeholder="07700 900000" />
          {showErr("phone") && <div className="field-err">{errs.phone}</div>}
        </div>
      </div>
      <div className="field">
        <label>Upload CV <span className="req">*</span></label>
        <label className={"dropzone" + (file ? " has" : "") + (showErr("file") ? " bad" : "")}>
          <Icon name={file ? "file-check" : "upload-cloud"} />
          <div style={{ fontFamily: "var(--font-display)", fontWeight: 700, color: "var(--slate-900)" }}>{file || "Drag & drop or browse"}</div>
          <div style={{ fontFamily: "var(--font-mono)", fontSize: 12, marginTop: 4 }}>PDF or Word · max 5 MB</div>
          <input type="file" accept=".pdf,.doc,.docx" style={{ display: "none" }} onChange={onFile} />
        </label>
        {showErr("file") && <div className="field-err">{errs.file}</div>}
      </div>
      {!compact && (
        <div className="field"><label>Message <span style={{ color: "var(--mist-400)", fontWeight: 400 }}>(optional)</span></label>
          <textarea className="input" value={f.msg} onChange={e => set("msg", e.target.value)} placeholder="A short note on why you're a great fit"></textarea></div>
      )}
      <label className={"consent" + (showErr("consent") ? " bad" : "")} style={{ marginBottom: showErr("consent") ? 6 : 22 }}>
        <input type="checkbox" checked={f.consent} onChange={e => { set("consent", e.target.checked); setErrs(validate()); }} />
        <span>I consent to Poolhall storing and processing my data to handle this application, per the <a href="#" onClick={e => e.preventDefault()}>Privacy Policy</a>. <span className="req">*</span></span>
      </label>
      {showErr("consent") && <div className="field-err" style={{ marginBottom: 18 }}>{errs.consent}</div>}
      <Button variant="primary" size="lg" className="btn-block" iconRight="send" type="submit">Submit application</Button>
      <div style={{ display: "flex", alignItems: "center", justifyContent: "center", gap: 7, marginTop: 14, fontFamily: "var(--font-mono)", fontSize: 12, color: "var(--mist-400)" }}>
        <Icon name="lock" style={{ width: 13, height: 13 }} /> Secure · GDPR-compliant · reCAPTCHA
      </div>
    </form>
  );
}

function ApplySuccess({ job, go }) {
  return (
    <div style={{ textAlign: "center", padding: "10px 0" }}>
      <div style={{ width: 64, height: 64, borderRadius: "var(--r)", background: "var(--hivis-50)", color: "var(--accent-ink)", display: "flex", alignItems: "center", justifyContent: "center", margin: "0 auto 18px" }}><Icon name="check" style={{ width: 30, height: 30 }} /></div>
      <h2 className="h2">You've applied</h2>
      <p className="lead" style={{ margin: "12px auto 0", maxWidth: "42ch" }}>Your application for <strong>{job.title}</strong> is with our team. We'll be in touch within 2 working days.</p>
      <div style={{ display: "flex", gap: 12, justifyContent: "center", marginTop: 26, flexWrap: "wrap" }}>
        <Button variant="primary" onClick={() => go("jobs")}>Browse more jobs</Button>
        <Button variant="ghost" onClick={() => go("home")}>Back home</Button>
      </div>
    </div>
  );
}

/* full Apply page (deep-link / no-JS fallback) -------------------------- */
function Apply({ go, job }) {
  const D = window.PH_DATA;
  const j = job || D.jobs[0];
  const F = window.PH_FMT;
  const [sent, setSent] = useState(false);
  return (
    <main className="bg-alt">
      <div className="container">
        <div className="crumb">
          <a href="#" onClick={e => { e.preventDefault(); go("home"); }}>Home</a><Icon name="chevron-right" />
          <a href="#" onClick={e => { e.preventDefault(); go("job", j); }}>{j.title}</a><Icon name="chevron-right" /><span>Apply</span>
        </div>
      </div>
      <div className="container" style={{ padding: "12px 32px 88px" }}>
        {sent ? (
          <div className="form-card" style={{ maxWidth: 600, margin: "40px auto" }}><ApplySuccess job={j} go={go} /></div>
        ) : (
          <div style={{ display: "grid", gridTemplateColumns: "1fr 320px", gap: 44, alignItems: "start" }} className="apply-cols">
            <div>
              <Label idx="//">Apply now</Label>
              <h1 className="h2">Apply for {j.title}</h1>
              <p className="lead" style={{ margin: "12px 0 26px" }}>Takes two minutes. Fields marked <span style={{ color: "var(--accent-ink)" }}>*</span> are required.</p>
              <div className="form-card"><ApplyForm job={j} onDone={() => setSent(true)} /></div>
            </div>
            <aside className="apply-aside">
              <span className={"jc-tag " + (window.PH_SECTOR[j.sector] || { key: "dig" }).key}>{j.sector}</span>
              <h3 className="jc-title" style={{ marginTop: 12 }}>{j.title}</h3>
              <div className="aside-meta" style={{ marginTop: 14 }}>
                <div className="r"><span>Salary</span><span>{F.salary(j)}</span></div>
                <div className="r"><span>Location</span><span>{j.location}</span></div>
                <div className="r"><span>Work mode</span><span>{j.work}</span></div>
                <div className="r"><span>Reference</span><span>#{j.id}</span></div>
              </div>
              <button className="btn-text" onClick={() => go("job", j)}>View full description <Icon name="arrow-right" /></button>
            </aside>
          </div>
        )}
      </div>
    </main>
  );
}

/* Apply MODAL — opens over the single-job page ------------------------- */
function ApplyModal({ job, open, onClose, go }) {
  const [sent, setSent] = useState(false);
  const F = window.PH_FMT;
  useEffect(() => {
    if (open) { setSent(false); document.body.style.overflow = "hidden"; }
    else document.body.style.overflow = "";
    const onKey = e => { if (e.key === "Escape" && open) onClose(); };
    document.addEventListener("keydown", onKey);
    return () => { document.removeEventListener("keydown", onKey); document.body.style.overflow = ""; };
  }, [open]);
  if (!open) return null;
  return (
    <div className="modal-overlay open" onMouseDown={e => { if (e.target === e.currentTarget) onClose(); }}>
      <div className="modal" role="dialog" aria-modal="true" aria-label={"Apply for " + job.title}>
        <div className="modal-head">
          <div>
            <span className={"jc-tag " + (window.PH_SECTOR[job.sector] || { key: "dig" }).key}>{job.sector}</span>
            <h3 style={{ color: "#fff", marginTop: 10, paddingRight: 36 }}>{job.title}</h3>
            <div className="modal-facts">
              <span><Icon name="map-pin" /> {job.location}</span>
              <span><Icon name="banknote" /> {F.salary(job)}</span>
              <span><Icon name="hash" /> {job.id}</span>
            </div>
          </div>
          <button className="modal-close" onClick={onClose} aria-label="Close"><Icon name="x" /></button>
        </div>
        <div className="modal-body">
          {sent ? <ApplySuccess job={job} go={r => { onClose(); go(r); }} /> : <ApplyForm job={job} compact onDone={() => setSent(true)} />}
        </div>
      </div>
    </div>
  );
}

/* candidate registration page ------------------------------------------ */
function Register({ go }) {
  const [sent, setSent] = useState(false);
  const [file, setFile] = useState(null);
  return (
    <main className="bg-alt">
      <div className="pagehead photo">
        <img src={window.PH_IMG.heroTeam} alt="" onError={e => e.target.style.display='none'} />
        <div className="container">
          <Label dark idx="//">For candidates</Label>
          <h1>Register your CV</h1>
          <p className="lead">One quick form and we'll be in touch the moment something right comes along. We'll always treat your details with care.</p>
        </div>
      </div>
      <div className="container" style={{ padding: "44px 32px 88px", maxWidth: 720 }}>
        {sent ? (
          <div className="form-card" style={{ textAlign: "center" }}>
            <div style={{ width: 60, height: 60, borderRadius: "var(--r)", background: "var(--hivis-50)", color: "var(--accent-ink)", display: "flex", alignItems: "center", justifyContent: "center", margin: "0 auto 16px" }}><Icon name="check" style={{ width: 28 }} /></div>
            <h2 className="h3">You're registered</h2>
            <p className="lead" style={{ margin: "8px auto 20px", maxWidth: "40ch" }}>Thanks, we've got your details. A consultant will be in touch soon.</p>
            <Button variant="primary" onClick={() => go("jobs")}>Browse jobs</Button>
          </div>
        ) : (
          <form className="form-card" onSubmit={e => { e.preventDefault(); setSent(true); }}>
            <div className="row2">
              <div className="field"><label>First name <span className="req">*</span></label><input className="input" required placeholder="Jane" /></div>
              <div className="field"><label>Last name <span className="req">*</span></label><input className="input" required placeholder="Smith" /></div>
            </div>
            <div className="row2">
              <div className="field"><label>Email <span className="req">*</span></label><input className="input" type="email" required placeholder="jane@email.com" /></div>
              <div className="field"><label>Phone <span className="req">*</span></label><input className="input" required placeholder="07700 900000" /></div>
            </div>
            <div className="field"><label>Sector of interest</label>
              <select className="input">{window.PH_DATA.sectors.map(s => <option key={s.name}>{s.name}</option>)}</select>
            </div>
            <div className="field">
              <label>Upload CV <span className="req">*</span></label>
              <label className={"dropzone" + (file ? " has" : "")}><Icon name={file ? "file-check" : "upload-cloud"} /><div style={{ fontFamily: "var(--font-display)", fontWeight: 700, color: "var(--slate-900)" }}>{file || "Drag & drop or browse"}</div><div style={{ fontFamily: "var(--font-mono)", fontSize: 12, marginTop: 4 }}>PDF or Word · max 5 MB</div><input type="file" accept=".pdf,.doc,.docx" style={{ display: "none" }} onChange={e => setFile(e.target.files[0]?.name)} /></label>
            </div>
            <label className="consent" style={{ marginBottom: 22 }}><input type="checkbox" required /><span>I consent to Poolhall holding my details per the <a href="#" onClick={e => e.preventDefault()}>Privacy Policy</a>. <span className="req">*</span></span></label>
            <Button variant="primary" size="lg" className="btn-block" iconRight="arrow-right" type="submit">Register now</Button>
          </form>
        )}
      </div>
    </main>
  );
}

Object.assign(window, { Apply, ApplyModal, ApplyForm, Register });
