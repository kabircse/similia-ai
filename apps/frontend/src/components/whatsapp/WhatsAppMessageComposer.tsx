import { useMemo, useState } from "react";
import { useMutation, useQuery } from "@tanstack/react-query";
import { Copy, ExternalLink, MessageCircle, RefreshCw } from "lucide-react";
import {
  getWhatsAppTemplates,
  renderWhatsAppMessage,
} from "../../lib/api";
import type { RenderedWhatsAppMessage } from "../../lib/api";

type Props = {
  patientId?: number | string | null;
  appointmentId?: number | string | null;
  defaultVariables?: Record<string, string | number | null>;
  compact?: boolean;
};

const CATEGORIES = [
  "appointment_reminder",
  "follow_up_reminder",
  "medicine_instruction",
  "prescription_follow_up",
  "missed_appointment",
  "portal_follow_up_request",
  "general_notice",
];

const LANGUAGES = ["bn", "en"];

export function WhatsAppMessageComposer({
  patientId,
  appointmentId,
  defaultVariables,
  compact = false,
}: Props) {
  const [category, setCategory] = useState("");
  const [language, setLanguage] = useState("");
  const [templateId, setTemplateId] = useState<number | "">("");
  const [preview, setPreview] = useState("");
  const [copied, setCopied] = useState(false);
  const [rendered, setRendered] = useState<RenderedWhatsAppMessage | null>(null);

  const templatesQuery = useQuery({
    queryKey: ["whatsapp-templates", category, language],
    queryFn: () =>
      getWhatsAppTemplates({
        category: category || null,
        language: language || null,
      }),
  });

  const templates = templatesQuery.data?.data ?? [];
  const selectedTemplateId = templateId || templates[0]?.id || "";

  const renderMutation = useMutation({
    mutationFn: () =>
      renderWhatsAppMessage({
        template_id: Number(selectedTemplateId),
        patient_id: patientId ?? null,
        appointment_id: appointmentId ?? null,
        variables: defaultVariables,
      }),
    onSuccess: (data) => {
      setRendered(data);
      setPreview(data.message);
      setCopied(false);
    },
  });

  const whatsappUrl = useMemo(() => {
    if (!rendered?.phone || !preview) {
      return null;
    }

    return `https://wa.me/${rendered.phone}?text=${encodeURIComponent(preview)}`;
  }, [preview, rendered?.phone]);

  async function copyMessage() {
    if (!preview || !navigator.clipboard) {
      return;
    }

    await navigator.clipboard.writeText(preview);
    setCopied(true);
  }

  return (
    <section className={compact ? "whatsapp-composer compact" : "panel whatsapp-composer"}>
      <div className="panel-heading panel-heading-between">
        <h3>
          <MessageCircle size={20} /> WhatsApp Message
        </h3>
      </div>

      <div className="whatsapp-template-grid">
        <label>
          Category
          <select
            className="method-select"
            value={category}
            onChange={(event) => {
              setCategory(event.target.value);
              setTemplateId("");
            }}
          >
            <option value="">All</option>
            {CATEGORIES.map((item) => (
              <option value={item} key={item}>
                {labelize(item)}
              </option>
            ))}
          </select>
        </label>

        <label>
          Language
          <select
            className="method-select"
            value={language}
            onChange={(event) => {
              setLanguage(event.target.value);
              setTemplateId("");
            }}
          >
            <option value="">All</option>
            {LANGUAGES.map((item) => (
              <option value={item} key={item}>
                {item.toUpperCase()}
              </option>
            ))}
          </select>
        </label>

        <label>
          Template
          <select
            className="method-select"
            value={selectedTemplateId}
            onChange={(event) => setTemplateId(Number(event.target.value))}
            disabled={templates.length === 0}
          >
            {templates.length === 0 && <option value="">No templates</option>}
            {templates.map((template) => (
              <option value={template.id} key={template.id}>
                {template.title}
              </option>
            ))}
          </select>
        </label>
      </div>

      <div className="inline-actions">
        <button
          className="secondary-button inline-button"
          type="button"
          onClick={() => renderMutation.mutate()}
          disabled={!selectedTemplateId || renderMutation.isPending}
        >
          <RefreshCw size={16} />
          {renderMutation.isPending ? "Rendering..." : "Preview"}
        </button>

        <button
          className="secondary-button inline-button"
          type="button"
          onClick={copyMessage}
          disabled={!preview}
        >
          <Copy size={16} />
          {copied ? "Copied" : "Copy"}
        </button>

        {whatsappUrl ? (
          <a
            className="primary-link inline-button"
            href={whatsappUrl}
            target="_blank"
            rel="noreferrer"
          >
            <ExternalLink size={16} />
            Open WhatsApp
          </a>
        ) : (
          <button className="primary-button inline-button" type="button" disabled>
            <ExternalLink size={16} />
            Open WhatsApp
          </button>
        )}
      </div>

      {renderMutation.isError && (
        <div className="form-error">Unable to render WhatsApp message.</div>
      )}

      {rendered && !rendered.phone && (
        <div className="form-error">Patient phone number is missing.</div>
      )}

      <label className="whatsapp-preview-field">
        Preview
        <textarea
          rows={compact ? 4 : 6}
          value={preview}
          onChange={(event) => {
            setPreview(event.target.value);
            setCopied(false);
          }}
          placeholder="Select a template and preview the message."
        />
      </label>
    </section>
  );
}

function labelize(value: string) {
  return value.replace(/_/g, " ").replace(/\b\w/g, (char) => char.toUpperCase());
}
