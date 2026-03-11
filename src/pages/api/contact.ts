import type { APIRoute } from "astro";
import { Resend } from "resend";

export const prerender = false;

export const POST: APIRoute = async ({ request }) => {
  try {
    const contentType = request.headers.get("content-type") || "";

    if (!contentType.includes("application/json")) {
      return new Response(
        JSON.stringify({
          ok: false,
          message: "El formulario debe enviar JSON.",
        }),
        {
          status: 400,
          headers: { "Content-Type": "application/json" },
        }
      );
    }

    const body = await request.json();

    const nombre = String(body?.nombre ?? "").trim();
    const empresa = String(body?.empresa ?? "").trim();
    const email = String(body?.email ?? "").trim();
    const telefono = String(body?.telefono ?? "").trim();
    const mensaje = String(body?.mensaje ?? "").trim();

    if (!nombre || !email || !mensaje) {
      return new Response(
        JSON.stringify({
          ok: false,
          message: "Nombre, email y mensaje son obligatorios.",
        }),
        {
          status: 400,
          headers: { "Content-Type": "application/json" },
        }
      );
    }

    const apiKey = import.meta.env.RESEND_API_KEY;
    const from = import.meta.env.RESEND_FROM_EMAIL || "onboarding@resend.dev";
    const to = import.meta.env.CONTACT_TO_EMAIL;

    if (!apiKey || !to) {
      return new Response(
        JSON.stringify({
          ok: false,
          message: "Faltan variables de entorno de Resend.",
        }),
        {
          status: 500,
          headers: { "Content-Type": "application/json" },
        }
      );
    }

    const resend = new Resend(apiKey);

    const html = `
      <div style="font-family:Arial,Helvetica,sans-serif;background:#f8fafc;padding:24px;">
        <div style="max-width:680px;margin:0 auto;background:#ffffff;border-radius:18px;padding:28px;border:1px solid #e2e8f0;">
          <div style="background:linear-gradient(135deg,#0C245C,#1e56b8);padding:20px 22px;border-radius:14px;color:#fff;">
            <div style="font-size:12px;letter-spacing:.15em;text-transform:uppercase;opacity:.9;font-weight:700;">
              Nuevo lead desde el sitio web
            </div>
            <h1 style="margin:10px 0 0;font-size:24px;line-height:1.2;">Solicitud de contacto</h1>
          </div>

          <div style="padding-top:24px;color:#0f172a;">
            <p><strong>Nombre:</strong> ${escapeHtml(nombre)}</p>
            <p><strong>Empresa:</strong> ${escapeHtml(empresa || "No especificada")}</p>
            <p><strong>Email:</strong> ${escapeHtml(email)}</p>
            <p><strong>Teléfono:</strong> ${escapeHtml(telefono || "No especificado")}</p>

            <div style="margin-top:18px;padding:18px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;">
              <p style="margin:0 0 8px;"><strong>Mensaje:</strong></p>
              <p style="margin:0;white-space:pre-line;">${escapeHtml(mensaje)}</p>
            </div>
          </div>
        </div>
      </div>
    `;

    const { error } = await resend.emails.send({
      from: `Resend <${from}>`,
      to: [to],
      replyTo: email,
      subject: `Nuevo contacto desde la web - ${nombre}`,
      html,
    });

    if (error) {
      console.error("Resend error:", error);

      return new Response(
        JSON.stringify({
          ok: false,
          message: "No se pudo enviar el correo.",
        }),
        {
          status: 500,
          headers: { "Content-Type": "application/json" },
        }
      );
    }

    return new Response(
      JSON.stringify({
        ok: true,
        message: "Mensaje enviado correctamente.",
      }),
      {
        status: 200,
        headers: { "Content-Type": "application/json" },
      }
    );
  } catch (error) {
    console.error("Error en /api/contact:", error);

    return new Response(
      JSON.stringify({
        ok: false,
        message: error instanceof Error ? error.message : "Error interno del servidor.",
      }),
      {
        status: 500,
        headers: { "Content-Type": "application/json" },
      }
    );
  }
};

function escapeHtml(value: string): string {
  return value
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#039;");
}