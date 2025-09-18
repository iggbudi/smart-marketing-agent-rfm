# Arsitektur Sistem BatikRFM - Versi 2

## Tech Stack

### Frontend
- **Framework**: Next.js 14 (App Router)
- **UI Library**: Tailwind CSS + shadcn/ui
- **State Management**: Zustand
- **Charts**: Recharts/Chart.js
- **Form Handling**: React Hook Form + Zod

### Backend
- **API**: Next.js API Routes
- **Authentication**: Supabase Auth
- **ORM**: Prisma
- **Job Queue**: Vercel Cron Jobs (untuk analisis mingguan)

### Database & Storage
- **Database**: PostgreSQL (via Supabase)
- **File Storage**: Supabase Storage
- **Cache**: Redis (optional, untuk scaling)

### AI/LLM Integration
- **Primary**: OpenAI API (GPT-3.5-turbo/GPT-4o-mini)
- **Backup**: Groq Cloud (Llama models)

### External Services
- **Email**: Resend
- **PDF Generation**: React PDF
- **Excel Processing**: ExcelJS
- **Future WhatsApp**: Fonnte/Wablas API

### Infrastructure
- **Hosting**: Vercel
- **Database**: Supabase
- **Monitoring**: Vercel Analytics
- **Error Tracking**: Sentry (optional)

## System Architecture Diagram

```
┌─────────────────┐     ┌─────────────────┐
│   Web Browser   │     │ Mobile Browser  │
└────────┬────────┘     └────────┬────────┘
         │                       │
         └───────────┬───────────┘
                     │ HTTPS
                     │
              ┌──────▼──────┐
              │   Vercel    │
              │  (Next.js)  │
              └──────┬──────┘
                     │
        ┌────────────┼────────────┐
        │            │            │
   ┌────▼────┐  ┌───▼───┐  ┌────▼────┐
   │   API   │  │  Auth │  │  Cron   │
   │ Routes  │  │       │  │  Jobs   │
   └────┬────┘  └───┬───┘  └────┬────┘
        │           │            │
        └───────────┼────────────┘
                    │
         ┌──────────▼──────────┐
         │     Supabase        │
         │  ┌──────┐ ┌──────┐  │
         │  │ Auth │ │ DB   │  │
         │  └──────┘ └──────┘  │
         │     ┌─────────┐     │
         │     │ Storage │     │
         │     └─────────┘     │
         └─────────────────────┘
                    │
      ┌─────────────┼─────────────┐
      │             │             │
 ┌────▼────┐  ┌────▼────┐  ┌────▼────┐
 │ OpenAI  │  │ Resend  │  │ WhatsApp│
 │   API   │  │  Email  │  │   API   │
 └─────────┘  └─────────┘  └─────────┘
```

## OpenAI Integration Details

### API Configuration
```typescript
// lib/openai.ts
import OpenAI from 'openai';

const openai = new OpenAI({
  apiKey: process.env.OPENAI_API_KEY,
});

// Gunakan GPT-3.5-turbo untuk cost optimization
export const generateMarketingContent = async (
  segment: string,
  customerData: any
) => {
  const completion = await openai.chat.completions.create({
    model: "gpt-3.5-turbo", // atau "gpt-4o-mini" untuk hasil lebih baik
    messages: [
      {
        role: "system",
        content: "Kamu adalah marketing expert untuk produk batik Indonesia."
      },
      {
        role: "user",
        content: `Buatkan konten marketing untuk segmen ${segment}...`
      }
    ],
    temperature: 0.7,
    max_tokens: 500,
  });
  
  return completion.choices[0].message.content;
};
```

### Cost Optimization Strategy

1. **Model Selection**:
   - Development/Testing: `gpt-3.5-turbo` ($0.0015/1K tokens)
   - Production: `gpt-4o-mini` ($0.00015/1K tokens) - 10x lebih murah!
   - Premium Feature: `gpt-4o` untuk konten kompleks

2. **Token Management**:
   - Cache hasil generate content per segmen
   - Batch processing untuk multiple contents
   - Limit max tokens per request

3. **Rate Limiting**:
   - Implement queue system
   - Max 10 content generation per business per day
   - Weekly batch processing untuk efisiensi

## Database Schema (Updated)

### Additional Tables for AI Integration

7. **ai_prompts**
   - id (UUID)
   - segment_type
   - prompt_template
   - max_tokens
   - temperature
   - created_at
   - updated_at

8. **generated_contents_cache**
   - id (UUID)
   - business_id (FK)
   - segment
   - content_hash
   - content
   - tokens_used
   - model_used
   - created_at
   - expires_at

## Environment Variables

```env
# Existing
DATABASE_URL=
NEXT_PUBLIC_SUPABASE_URL=
NEXT_PUBLIC_SUPABASE_ANON_KEY=
SUPABASE_SERVICE_ROLE_KEY=

# OpenAI Integration
OPENAI_API_KEY=
OPENAI_ORG_ID= (optional)
OPENAI_DEFAULT_MODEL=gpt-4o-mini
OPENAI_MAX_TOKENS=500
OPENAI_TEMPERATURE=0.7

# Email Service
RESEND_API_KEY=

# Future WhatsApp
FONNTE_API_KEY=
```

## API Endpoints (Updated)

### Content Generation Endpoints
- `POST /api/ai/generate-content`
  - Generate marketing content for specific segment
  - Rate limited: 10 requests/day/business

- `POST /api/ai/batch-generate`
  - Weekly batch generation for all segments
  - Called by cron job

- `GET /api/ai/usage`
  - Track OpenAI API usage and costs

## Cost Estimation (Updated)

### OpenAI API Costs
- **gpt-4o-mini**: 
  - Input: $0.00015/1K tokens
  - Output: $0.0006/1K tokens
  - Est. per content: ~500 tokens = $0.0004
  
- **Monthly estimation** (100 UMKM):
  - 100 businesses × 5 segments × 4 weeks = 2,000 contents
  - 2,000 × $0.0004 = $0.80/month (~Rp 12,500)

### Total Operational Cost (Updated)
- Starting (0-100 users): 
  - Infrastructure: Rp 0 (free tier)
  - OpenAI: ~Rp 15,000/bulan
  - **Total**: ~Rp 15,000/bulan

- Growth (100-500 users):
  - Infrastructure: Rp 300,000/bulan
  - OpenAI: ~Rp 75,000/bulan
  - **Total**: ~Rp 375,000/bulan

## Security Considerations (AI-Specific)

1. **API Key Management**:
   - Store in environment variables
   - Never expose in client-side code
   - Rotate keys regularly

2. **Content Moderation**:
   - Implement content filtering
   - Review generated content before sending
   - Log all AI interactions

3. **Rate Limiting**:
   - Per-business daily limits
   - Global rate limiting for API protection
   - Queue system for batch processing

4. **Data Privacy**:
   - Don't send sensitive customer data to OpenAI
   - Anonymize data when possible
   - Comply with data protection regulations