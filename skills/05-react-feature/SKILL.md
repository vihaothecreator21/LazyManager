---
name: 05-react-feature
description: Chuẩn hóa cách xây React feature cho LazyManager MVP. Dùng khi implement hoặc review frontend feature với React, TypeScript, Vite, React Router, TanStack Query, React Hook Form, Zod, API qua Nginx, state handling, authorization UI, tests và Definition of Done.
---

# LazyManager MVP - Skill React Feature

## 1. Mục đích

Skill này hướng dẫn xây một React feature hoàn chỉnh cho LazyManager MVP, kết nối REST API qua Nginx API Gateway.

Mục tiêu là tạo frontend đơn giản, rõ, chạy end-to-end với backend, xử lý đủ loading/error/empty states, dùng type an toàn và không để API logic rải rác trong component.

Luôn tuân thủ `skills/00-project-context/SKILL.md`. Không thêm UI phức tạp, dashboard nâng cao hoặc animation không phục vụ use case MVP.

## 2. Công nghệ

Frontend LazyManager MVP dùng:

- React.
- TypeScript.
- Vite.
- React Router.
- TanStack Query.
- React Hook Form.
- Zod.
- Axios hoặc một HTTP client thống nhất.

Không trộn nhiều HTTP client trong cùng project.

## 3. Cấu trúc thư mục feature

Cấu trúc frontend chuẩn:

```text
src/
├── app/
├── components/
├── lib/
├── routes/
└── features/
    └── feature-name/
        ├── api/
        ├── components/
        ├── hooks/
        ├── pages/
        ├── schemas/
        └── types/
```

Ý nghĩa:

- `app/`: cấu hình app-level như router, query client, providers.
- `components/`: component dùng chung.
- `lib/`: HTTP client, auth helpers, utilities dùng chung.
- `routes/`: route definitions hoặc route wrappers.
- `features/<feature-name>/api`: API functions của feature.
- `features/<feature-name>/components`: component nội bộ feature.
- `features/<feature-name>/hooks`: query/mutation hooks.
- `features/<feature-name>/pages`: page gắn với route.
- `features/<feature-name>/schemas`: Zod schema.
- `features/<feature-name>/types`: TypeScript types.

## 4. Trạng thái bắt buộc

Mỗi trang dữ liệu phải xử lý:

- Loading.
- Empty.
- Success.
- Validation error.
- API error.
- Unauthorized.
- Forbidden.

Không để màn hình trắng khi request lỗi.

Không chỉ log lỗi ra console. Người dùng phải thấy trạng thái phù hợp.

## 5. Quy tắc API

- Base URL phải đi qua Nginx.
- Không gọi thẳng port service như `localhost:8001` hoặc `localhost:8002` từ React.
- Token được gắn bằng interceptor.
- `401` chuyển về login.
- `403` hiển thị thông báo không có quyền.
- Không để API logic rải rác trong component.
- API functions đặt trong `features/<feature-name>/api` hoặc `src/lib` nếu dùng chung.
- Response và error shape phải được type hóa.

Ví dụ hướng route qua Nginx:

```text
/api/people/v1/auth/login
/api/people/v1/employees
/api/inventory/v1/products
```

## 6. Quy tắc form

- Dùng React Hook Form.
- Dùng Zod schema.
- Hiển thị lỗi từng field.
- Disable nút khi đang submit.
- Không submit lặp.
- Reset hoặc redirect sau success.
- Hiển thị lỗi API ở vị trí dễ thấy.
- Với thao tác quan trọng, yêu cầu xác nhận trước khi submit.

Form data phải có type, thường suy ra từ Zod schema.

## 7. Quy tắc TanStack Query

- Query key rõ ràng.
- Mutation thành công phải invalidate đúng query.
- Không lưu server state trùng trong `useState`.
- Không gọi API thủ công trong `useEffect` nếu TanStack Query phù hợp.
- Dùng `enabled` cho query phụ thuộc input.
- Dùng mutation state để disable nút và hiển thị submit progress.

Ví dụ query key:

```ts
['employees']
['employee', employeeId]
['schedule', week]
['products']
['inventory', filters]
['stock-count', stockCountId]
```

## 8. UI phân quyền

- `STAFF` không thấy nút thêm, sửa và xóa nhân viên.
- `STORE_MANAGER` thấy các thao tác employee mutation.
- UI có thể ẩn action theo role để giảm nhầm lẫn.
- Backend vẫn là nguồn kiểm tra quyền.
- Không dùng frontend role check thay cho backend security.
- Nếu backend trả `403`, UI phải hiển thị thông báo không có quyền.

Các module khác trong MVP cho phép cả `STORE_MANAGER` và `STAFF` thao tác, trừ employee create/update/delete.

## 9. Quy tắc TypeScript

- Không dùng `any` trừ khi có giải thích.
- API response phải có type.
- Form data phải có type.
- Dùng enum hoặc union cho role, status và shift type.
- Không để object API response không rõ shape đi xuyên qua UI.
- Component props phải có type rõ.

Ví dụ union:

```ts
type UserRole = 'STORE_MANAGER' | 'STAFF';
type ShiftType = 'MORNING' | 'AFTERNOON';
type DailySalesStatus = 'DRAFT' | 'CONFIRMED' | 'CANCELLED';
```

## 10. Quy tắc UX

- Giao diện desktop/tablet responsive.
- Không ưu tiên animation hoặc thiết kế phức tạp.
- Tập trung thao tác nhanh.
- Bảng phải có loading và empty state.
- Form phải xác nhận khi thao tác có thể gây thay đổi dữ liệu quan trọng.
- Layout ưu tiên form/table rõ ràng.
- Không làm drag-and-drop cho schedule trong MVP.
- Không làm dashboard nâng cao trong MVP.
- Text lỗi phải dễ hiểu, không lộ stack trace.

Các thao tác nên dễ demo theo golden path:

- Login.
- Employee list/form.
- Weekly schedule.
- Product/SKU CRUD.
- Inventory import.
- Daily sales.
- Borrow/return.
- Stock count.

## 11. Quy trình implementation

Khi xây một React feature, thực hiện theo thứ tự:

1. Đọc API contract.
2. Tạo types.
3. Tạo schema.
4. Tạo API functions.
5. Tạo query hoặc mutation hooks.
6. Tạo components.
7. Tạo page.
8. Cấu hình route.
9. Xử lý role.
10. Kiểm thử.
11. Kiểm tra console.

Nếu API contract chưa rõ, không tự bịa field phức tạp. Đọc backend route/resource hiện tại hoặc ghi rõ assumption trong plan.

## 12. Test bắt buộc

Mỗi feature cần test phù hợp:

- Page render.
- Loading.
- API error.
- Validation.
- Mutation success.
- `STAFF` không thấy employee mutation buttons.

Test nên kiểm tra behavior người dùng thấy được, không bám quá chặt vào implementation detail.

Với form mutation, test tối thiểu:

- Submit valid data gọi mutation.
- Invalid data hiển thị field error.
- Submit button disabled khi đang submit.
- Success invalidate hoặc cập nhật UI đúng.

## 13. Tiêu chí hoàn thành

React feature chỉ được xem là Done khi:

- React gọi API qua Nginx.
- Không có TypeScript error.
- Không có console error.
- Loading, empty và error hoạt động.
- Mutation cập nhật lại UI.
- Responsive cơ bản.
- API logic không nằm rải rác trong component.
- Form validate bằng Zod và React Hook Form.
- Role UI đúng với MVP.
- Backend vẫn xử lý authorization thật.

## 14. Định dạng output

Chỉ trả nội dung `SKILL.md`.
